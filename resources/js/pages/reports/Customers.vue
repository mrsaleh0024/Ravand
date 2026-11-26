<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatAmount, formatJalaliDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { show as customersShow } from '@/routes/customers';
import { index as reportsCustomers } from '@/routes/reports/customers';
import type { CustomerReportRow, TagItem } from '@/types';

interface PaginatedRows {
    data: CustomerReportRow[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    rows: PaginatedRows;
    filters: { tag: string; sort: string };
    tags: TagItem[];
    currency: string;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('reports.customers.title'),
                href: reportsCustomers(),
            },
        ],
    },
});

const tag = ref(props.filters.tag || 'all');
const sort = ref(props.filters.sort || 'last_purchase');

watch(tag, () => applyFilters());
watch(sort, () => applyFilters());

const applyFilters = (patch: Record<string, string> = {}) => {
    router.get(
        reportsCustomers(),
        {
            tag: tag.value === 'all' ? '' : tag.value,
            sort: sort.value,
            ...patch,
        },
        { preserveState: true, replace: true },
    );
};

const totalPages = computed(() => Math.max(1, props.rows.last_page));

const goToPage = (page: number) => {
    if (page < 1 || page > totalPages.value) return;

    router.get(
        reportsCustomers(),
        {
            page: String(page),
            tag: tag.value === 'all' ? '' : tag.value,
            sort: sort.value,
        },
        { preserveState: true },
    );
};

const sortOptions = ['last_purchase', 'purchase_count', 'total_spent', 'name'] as const;
</script>

<template>
    <Head :title="t('reports.customers.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                :title="t('reports.customers.title')"
                :description="t('reports.customers.description')"
            />
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <Label for="report-tag">{{ t('customers.tags') }}</Label>
                    <Select v-model:model-value="tag">
                        <SelectTrigger id="report-tag" class="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {{ t('reports.customers.allTags') }}
                            </SelectItem>
                            <SelectItem
                                v-for="option in tags"
                                :key="option.slug"
                                :value="option.slug"
                            >
                                {{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <Label for="report-sort">
                        {{ t('reports.customers.sort') }}
                    </Label>
                    <Select v-model:model-value="sort">
                        <SelectTrigger id="report-sort" class="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in sortOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ t(`reports.customers.sort.${option}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t('reports.customers.title') }}
                    <span class="text-sm font-normal text-muted-foreground">
                        ({{ rows.total.toLocaleString('fa-IR') }})
                    </span>
                </CardTitle>
                <CardDescription class="text-xs leading-6">
                    {{ t('reports.customers.definitions') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('common.name') }}</TableHead>
                            <TableHead>{{ t('customers.tags') }}</TableHead>
                            <TableHead class="text-center">
                                {{ t('reports.customers.purchaseCount') }}
                            </TableHead>
                            <TableHead class="text-left">
                                {{ t('reports.customers.totalSpent') }}
                            </TableHead>
                            <TableHead class="text-left">
                                {{ t('reports.customers.lastPurchase') }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="rows.data.length === 0"
                            :colspan="5"
                        >
                            {{ t('reports.customers.empty') }}
                        </TableEmpty>
                        <TableRow v-for="row in rows.data" :key="row.id">
                            <TableCell>
                                <Link
                                    :href="customersShow({ customer: row.id })"
                                    class="font-medium hover:underline"
                                >
                                    {{ row.display_name }}
                                </Link>
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                    dir="ltr"
                                >
                                    {{ row.customer_number }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="item in row.tags"
                                        :key="item.id"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ item.name }}
                                    </Badge>
                                    <span
                                        v-if="row.tags.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ t('common.notAvailable') }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center tabular-nums">
                                {{ row.purchase_count.toLocaleString('fa-IR') }}
                            </TableCell>
                            <TableCell class="tabular-nums" dir="ltr">
                                {{
                                    row.total_spent === '0'
                                        ? t('common.notAvailable')
                                        : formatAmount(row.total_spent, currency)
                                }}
                            </TableCell>
                            <TableCell class="text-sm">
                                {{
                                    row.last_purchase_at
                                        ? formatJalaliDate(row.last_purchase_at)
                                        : t('reports.customers.never')
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Server-side pagination -->
                <div
                    v-if="rows.last_page > 1"
                    class="mt-4 flex items-center justify-between text-sm"
                >
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 disabled:opacity-50"
                        :disabled="rows.current_page <= 1"
                        @click="goToPage(rows.current_page - 1)"
                    >
                        {{ t('common.back') }}
                    </button>
                    <span class="text-muted-foreground">
                        {{ rows.current_page.toLocaleString('fa-IR') }} /
                        {{ totalPages.toLocaleString('fa-IR') }}
                    </span>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 disabled:opacity-50"
                        :disabled="rows.current_page >= rows.last_page"
                        @click="goToPage(rows.current_page + 1)"
                    >
                        {{ t('common.next') }}
                    </button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
