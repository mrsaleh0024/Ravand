<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, UserRound } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import iranLocations from '@/data/iran-provinces-cities.json';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatJalaliDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { show as customersShow, index as customersIndex } from '@/routes/customers';
import type { CustomerListItem, DuplicateMatch, TagItem } from '@/types';

interface PaginatedCustomers {
    data: CustomerListItem[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    customers: PaginatedCustomers;
    filters: { search: string; status: string; tag: string; channel: string };
    tags: TagItem[];
    duplicates: DuplicateMatch[];
    draft: Record<string, string>;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('customers.title'),
                href: customersIndex(),
            },
        ],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status || 'default');
const tag = ref(props.filters.tag || 'all');
const channel = ref(props.filters.channel || 'all');

// Debounced server-side filtering (spec 7.1: search runs on the server).
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: value }), 350);
});

watch(status, () => applyFilters());
watch(tag, () => applyFilters());
watch(channel, () => applyFilters());

const applyFilters = (patch: Record<string, string> = {}) => {
    router.get(
        customersIndex(),
        {
            search: search.value,
            status: status.value === 'default' ? '' : status.value,
            tag: tag.value === 'all' ? '' : tag.value,
            channel: channel.value === 'all' ? '' : channel.value,
            ...patch,
        },
        { preserveState: true, replace: true },
    );
};

// --- Create dialog (short form, spec 6.5) ---------------------------------

const createOpen = ref(props.duplicates.length > 0);
const draftForm = reactive({
    display_name: props.draft.display_name ?? '',
    phone: props.draft.phone ?? '',
    social_platform: props.draft.social_platform ?? 'instagram',
    social_handle: props.draft.social_handle ?? '',
    acquisition_channel: props.draft.acquisition_channel ?? 'unknown',
    province: props.draft.province ?? '',
    city: props.draft.city ?? '',
    note: props.draft.note ?? '',
});

const provinces = iranLocations.provinces;
const availableCities = computed(() =>
    provinces.find((province) => province.name === draftForm.province)?.cities ?? [],
);

watch(() => draftForm.province, () => {
    if (!availableCities.value.includes(draftForm.city)) draftForm.city = '';
});

const statusVariant = (value: string) =>
    (
        {
            active: 'default',
            inactive: 'secondary',
            blocked: 'destructive',
            archived: 'outline',
        } as const
    )[value] ?? 'secondary';

const channelLabel = (value: string) => t(`customers.channel.${value}`);

const totalPages = computed(() => Math.max(1, props.customers.last_page));

const goToPage = (page: number) => {
    if (page < 1 || page > totalPages.value) return;
    router.get(
        customersIndex(),
        { page: String(page), search: search.value },
        { preserveState: true },
    );
};
</script>

<template>
    <Head :title="t('customers.title')" />

    <div class="space-y-6">
        <Heading
            :title="t('customers.title')"
            :description="t('customers.description')"
        >
            <template #actions>
                <Button @click="createOpen = true">
                    <Plus />
                    {{ t('customers.create') }}
                </Button>
            </template>
        </Heading>

        <!-- Search + filters -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <div class="relative w-full md:max-w-xs">
                <Search
                    class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="t('customers.searchPlaceholder')"
                    class="pr-9"
                />
            </div>

            <Select v-model="status">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue :placeholder="t('common.status')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="default">
                        {{ t('customers.allStatuses') }}
                    </SelectItem>
                    <SelectItem value="active">
                        {{ t('customers.status.active') }}
                    </SelectItem>
                    <SelectItem value="inactive">
                        {{ t('customers.status.inactive') }}
                    </SelectItem>
                    <SelectItem value="blocked">
                        {{ t('customers.status.blocked') }}
                    </SelectItem>
                    <SelectItem value="archived">
                        {{ t('customers.status.archived') }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="tag">
                <SelectTrigger class="w-full md:w-40">
                    <SelectValue :placeholder="t('customers.tags')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">
                        {{ t('customers.allTags') }}
                    </SelectItem>
                    <SelectItem v-for="item in tags" :key="item.id" :value="item.slug">
                        {{ item.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="channel">
                <SelectTrigger class="w-full md:w-40">
                    <SelectValue :placeholder="t('customers.channel')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">
                        {{ t('customers.allChannels') }}
                    </SelectItem>
                    <SelectItem value="instagram">
                        {{ t('customers.channel.instagram') }}
                    </SelectItem>
                    <SelectItem value="whatsapp">
                        {{ t('customers.channel.whatsapp') }}
                    </SelectItem>
                    <SelectItem value="telegram">
                        {{ t('customers.channel.telegram') }}
                    </SelectItem>
                    <SelectItem value="referral">
                        {{ t('customers.channel.referral') }}
                    </SelectItem>
                    <SelectItem value="in_person">
                        {{ t('customers.channel.in_person') }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Customer table -->
        <Card>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('customers.number') }}</TableHead>
                            <TableHead>{{ t('customers.name') }}</TableHead>
                            <TableHead>{{ t('customers.phone') }}</TableHead>
                            <TableHead class="hidden md:table-cell">
                                {{ t('customers.city') }}
                            </TableHead>
                            <TableHead class="hidden lg:table-cell">
                                {{ t('customers.channel') }}
                            </TableHead>
                            <TableHead class="hidden lg:table-cell">
                                {{ t('customers.tags') }}
                            </TableHead>
                            <TableHead>{{ t('common.status') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">
                                {{ t('customers.createdAt') }}
                            </TableHead>
                            <TableHead>{{ t('common.actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="customers.data.length === 0" :colspan="9">
                            <div class="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                                <UserRound class="size-8" />
                                <span>{{ t('customers.empty') }}</span>
                                <span class="text-xs">{{ t('customers.emptyHint') }}</span>
                            </div>
                        </TableEmpty>
                        <TableRow v-for="customer in customers.data" :key="customer.id">
                            <TableCell dir="ltr" class="font-mono text-xs">
                                {{ customer.customer_number }}
                            </TableCell>
                            <TableCell>
                                <Link
                                    :href="customersShow({ customer: customer.id })"
                                    class="font-medium hover:underline"
                                >
                                    {{ customer.display_name }}
                                </Link>
                            </TableCell>
                            <TableCell dir="ltr" class="text-left">
                                {{ customer.phone ?? '—' }}
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                {{ customer.city ?? '—' }}
                            </TableCell>
                            <TableCell class="hidden text-sm text-muted-foreground lg:table-cell">
                                {{ channelLabel(customer.channel) }}
                            </TableCell>
                            <TableCell class="hidden lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="item in customer.tags"
                                        :key="item.id"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ item.name }}
                                    </Badge>
                                    <span v-if="customer.tags.length === 0" class="text-muted-foreground">—</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="statusVariant(customer.status)">
                                    {{ t(`customers.status.${customer.status}`) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="hidden text-xs text-muted-foreground sm:table-cell">
                                {{ formatJalaliDate(customer.created_at) }}
                            </TableCell>
                            <TableCell>
                                <Button as-child variant="outline" size="sm">
                                    <Link :href="customersShow({ customer: customer.id })">
                                        {{ t('customers.openProfile') }}
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Server-side pagination -->
                <div
                    v-if="totalPages > 1"
                    class="mt-4 flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ customers.total.toLocaleString('fa-IR') }}</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="customers.current_page <= 1"
                            @click="goToPage(customers.current_page - 1)"
                        >
                            {{ t('common.back') }}
                        </Button>
                        <span dir="ltr">
                            {{ customers.current_page }} / {{ totalPages }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="customers.current_page >= totalPages"
                            @click="goToPage(customers.current_page + 1)"
                        >
                            بعدی
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Create dialog: short form + non-blocking duplicate warning (spec 7.2) -->
    <Dialog
        :open="createOpen"
        @update:open="(value: boolean) => (createOpen = value)"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('customers.createTitle') }}</DialogTitle>
                <DialogDescription>
                    {{ t('customers.createDescription') }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="duplicates.length > 0"
                class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800/40 dark:bg-amber-950/30"
            >
                <p class="mb-2 font-medium text-amber-900 dark:text-amber-300">
                    {{ t('customers.duplicateWarning') }}
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="match in duplicates"
                        :key="match.id"
                        class="flex items-center justify-between gap-2"
                    >
                        <Link
                            :href="customersShow({ customer: match.id })"
                            class="font-medium underline"
                        >
                            {{ match.display_name }}
                        </Link>
                        <span dir="ltr" class="font-mono text-xs text-muted-foreground">
                            {{ match.customer_number }}
                            <template v-if="match.phone"> · {{ match.phone }}</template>
                        </span>
                    </li>
                </ul>
                <p class="mt-2 text-xs text-amber-800 dark:text-amber-400">
                    {{ t('customers.duplicateText') }}
                </p>
            </div>

            <Form
                v-bind="CustomerController.store.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
                @success="createOpen = false"
            >
                <div class="grid gap-2">
                    <Label for="display_name">
                        {{ t('customers.displayName') }} *
                    </Label>
                    <Input
                        id="display_name"
                        name="display_name"
                        required
                        v-model="draftForm.display_name"
                        :placeholder="t('customers.displayNamePlaceholder')"
                    />
                    <InputError :message="errors.display_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">{{ t('customers.createPhone') }}</Label>
                    <Input
                        id="phone"
                        name="phone"
                        dir="ltr"
                        inputmode="tel"
                        v-model="draftForm.phone"
                        placeholder="۰۹۱۲…"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="grid content-end gap-2">
                        <Select
                            name="social_platform"
                            v-model="draftForm.social_platform"
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="instagram">
                                    {{ t('customers.social.platform.instagram') }}
                                </SelectItem>
                                <SelectItem value="telegram">
                                    {{ t('customers.social.platform.telegram') }}
                                </SelectItem>
                                <SelectItem value="whatsapp">
                                    {{ t('customers.social.platform.whatsapp') }}
                                </SelectItem>
                                <SelectItem value="eitaa">
                                    {{ t('customers.social.platform.eitaa') }}
                                </SelectItem>
                                <SelectItem value="bale">
                                    {{ t('customers.social.platform.bale') }}
                                </SelectItem>
                                <SelectItem value="rubika">
                                    {{ t('customers.social.platform.rubika') }}
                                </SelectItem>
                                <SelectItem value="other">
                                    {{ t('customers.social.platform.other') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="col-span-2 grid gap-2">
                        <Label for="social_handle">
                            {{ t('customers.createSocial') }}
                        </Label>
                        <Input
                            id="social_handle"
                            name="social_handle"
                            dir="ltr"
                            v-model="draftForm.social_handle"
                            placeholder="@username"
                        />
                        <InputError :message="errors.social_handle" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="acquisition_channel">
                        {{ t('customers.createChannel') }}
                    </Label>
                    <Select
                        name="acquisition_channel"
                        v-model="draftForm.acquisition_channel"
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="unknown">
                                {{ t('customers.channel.unknown') }}
                            </SelectItem>
                            <SelectItem value="instagram">
                                {{ t('customers.channel.instagram') }}
                            </SelectItem>
                            <SelectItem value="whatsapp">
                                {{ t('customers.channel.whatsapp') }}
                            </SelectItem>
                            <SelectItem value="telegram">
                                {{ t('customers.channel.telegram') }}
                            </SelectItem>
                            <SelectItem value="referral">
                                {{ t('customers.channel.referral') }}
                            </SelectItem>
                            <SelectItem value="in_person">
                                {{ t('customers.channel.in_person') }}
                            </SelectItem>
                            <SelectItem value="advertisement">
                                {{ t('customers.channel.advertisement') }}
                            </SelectItem>
                            <SelectItem value="other">
                                {{ t('customers.channel.other') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <details class="rounded-md border p-3" open>
                    <summary class="cursor-pointer font-medium">{{ t('customers.createProvince') }} / {{ t('customers.createCity') }}</summary>
                    <div class="mt-3 grid gap-3">
                        <div class="grid gap-2">
                            <Label for="province">{{ t('customers.createProvince') }}</Label>
                            <select id="province" v-model="draftForm.province" name="province" class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <option value="">{{ t('customers.selectProvince') }}</option>
                                <option v-for="province in provinces" :key="province.id" :value="province.name">{{ province.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="city">{{ t('customers.createCity') }}</Label>
                            <select id="city" v-model="draftForm.city" name="city" :disabled="!draftForm.province" class="h-10 w-full rounded-md border bg-background px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="">{{ t('customers.selectCity') }}</option>
                                <option v-for="city in availableCities" :key="city" :value="city">{{ city }}</option>
                            </select>
                        </div>
                    </div>
                </details>

                <div class="grid gap-2">
                    <Label for="note">{{ t('customers.createNote') }}</Label>
                    <Input id="note" name="note" v-model="draftForm.note" />
                </div>

                <DialogFooter class="gap-2">
                    <label
                        v-if="duplicates.length > 0"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox name="force" />
                        {{ t('customers.duplicateCreateAnyway') }}
                    </label>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
