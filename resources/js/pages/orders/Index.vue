<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Package, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { formatAmount, formatJalaliDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    create as ordersCreate,
    index as ordersIndex,
    show as ordersShow,
} from '@/routes/orders';
import type { OrderListItem, Paginated } from '@/types';

interface Props {
    orders: Paginated<OrderListItem>;
    filters: { search: string; status: string; channel: string };
    statuses: string[];
    channels: string[];
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('orders.title'), href: ordersIndex().url }],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status || 'all');
const channel = ref(props.filters.channel || 'all');

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: value }), 350);
});

watch(status, () => applyFilters());
watch(channel, () => applyFilters());

const applyFilters = (patch: Record<string, string> = {}) => {
    router.get(
        ordersIndex().url,
        {
            search: search.value,
            status: status.value === 'all' ? '' : status.value,
            channel: channel.value === 'all' ? '' : channel.value,
            ...patch,
        },
        { preserveState: true, replace: true },
    );
};

const statusVariant = (value: string) =>
    (
        {
            draft: 'secondary',
            confirmed: 'default',
            processing: 'default',
            shipped: 'default',
            delivered: 'outline',
            completed: 'outline',
            cancelled: 'destructive',
            returned: 'destructive',
        } as const
    )[value] ?? 'secondary';

const paymentVariant = (value: string) =>
    (
        {
            unpaid: 'destructive',
            partially_paid: 'secondary',
            paid: 'default',
            refunded: 'outline',
        } as const
    )[value] ?? 'secondary';

const totalPages = computed(() => Math.max(1, props.orders.last_page));

const goToPage = (pageNumber: number) => {
    if (pageNumber < 1 || pageNumber > totalPages.value) return;
    router.get(ordersIndex().url, { page: String(pageNumber) }, { preserveState: true });
};
</script>

<template>
    <Head :title="t('orders.title')" />

    <div class="space-y-6">
        <Heading :title="t('orders.title')" :description="t('orders.description')">
            <template #actions>
                <Button as-child>
                    <Link :href="ordersCreate().url">
                        <Plus />
                        {{ t('orders.create') }}
                    </Link>
                </Button>
            </template>
        </Heading>

        <!-- Filters -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <div class="relative w-full md:max-w-xs">
                <Search class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="search" :placeholder="t('orders.searchPlaceholder')" class="pr-9" />
            </div>

            <Select v-model="status">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue :placeholder="t('common.status')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ t('catalog.allStates') }}</SelectItem>
                    <SelectItem v-for="item in statuses" :key="item" :value="item">
                        {{ t(`orders.status.${item}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="channel">
                <SelectTrigger class="w-full md:w-40">
                    <SelectValue :placeholder="t('orders.salesChannel')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ t('catalog.allStates') }}</SelectItem>
                    <SelectItem v-for="item in channels" :key="item" :value="item">
                        {{ t(`orders.channel.${item}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Orders table -->
        <Card>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('orders.number') }}</TableHead>
                            <TableHead>{{ t('orders.customer') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ t('orders.itemsCount') }}</TableHead>
                            <TableHead>{{ t('orders.total') }}</TableHead>
                            <TableHead>{{ t('orders.paymentStatus') }}</TableHead>
                            <TableHead>{{ t('common.status') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">{{ t('orders.placedAt') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="orders.data.length === 0" :colspan="7">
                            <div class="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                                <Package class="size-8" />
                                <span>{{ t('orders.empty') }}</span>
                                <span class="text-xs">{{ t('orders.emptyHint') }}</span>
                            </div>
                        </TableEmpty>
                        <TableRow v-for="order in orders.data" :key="order.id">
                            <TableCell dir="ltr" class="font-mono text-xs">
                                <Link
                                    :href="ordersShow({ order: order.id }).url"
                                    class="font-medium hover:underline"
                                >
                                    {{ order.order_number }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ order.customer_name }}</span>
                                    <span class="text-xs text-muted-foreground" dir="ltr">
                                        {{ order.customer_number }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                {{ order.items_count.toLocaleString('fa-IR') }}
                            </TableCell>
                            <TableCell dir="ltr" class="whitespace-nowrap text-sm">
                                {{ formatAmount(order.total_amount, order.currency_code) }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="paymentVariant(order.payment_status)">
                                    {{ t(`orders.payment.${order.payment_status}`) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="statusVariant(order.status)">
                                    {{ t(`orders.status.${order.status}`) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="hidden text-xs text-muted-foreground sm:table-cell">
                                {{ formatJalaliDateTime(order.placed_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ orders.total.toLocaleString('fa-IR') }}</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="orders.current_page <= 1"
                            @click="goToPage(orders.current_page - 1)"
                        >
                            ‹
                        </Button>
                        <span>
                            {{ orders.current_page.toLocaleString('fa-IR') }} /
                            {{ totalPages.toLocaleString('fa-IR') }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="orders.current_page >= totalPages"
                            @click="goToPage(orders.current_page + 1)"
                        >
                            ›
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
