<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { PackageSearch, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { formatJalaliDateTime, toAsciiDigits } from '@/lib/format';
import { t } from '@/lib/i18n';
import { index as inventoryIndex } from '@/routes/inventory';
import { store as movementsStore } from '@/routes/inventory/movements';
import type { MovementRow, MovementTypeCode, Paginated, StockRow, VariantOption } from '@/types';

interface Props {
    stock: Paginated<StockRow>;
    movements: Paginated<MovementRow>;
    variantOptions: VariantOption[];
    filters: { search: string; variant: string };
}

const props = defineProps<Props>();

const page = usePage();
const canManage = computed(() => page.props.auth.role === 'owner' || page.props.auth.role === 'manager');

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('inventory.title'), href: inventoryIndex().url }],
    },
});

// --- Stock filters -----------------------------------------------------------

const search = ref(props.filters.search);
const movementVariant = ref(props.filters.variant || 'all');

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: value }), 350);
});

watch(movementVariant, () => applyFilters());

const applyFilters = (patch: Record<string, string> = {}) => {
    router.get(
        inventoryIndex().url,
        {
            search: search.value,
            variant: movementVariant.value === 'all' ? '' : movementVariant.value,
            ...patch,
        },
        { preserveState: true, replace: true },
    );
};

// --- Movement recording dialog -------------------------------------------------

const MANUAL_TYPES: MovementTypeCode[] = ['opening', 'purchase_in', 'adjustment_in', 'adjustment_out'];

const movementOpen = ref(false);
const movementForm = ref({
    product_variant_id: '',
    movement_type: 'opening',
    quantity: '',
    reason: '',
    idempotency_key: '',
});
const movementErrors = ref<Record<string, string>>({});
const movementSending = ref(false);

const submitMovement = () => {
    movementSending.value = true;
    movementErrors.value = {};

    router.post(
        movementsStore().url,
        {
            product_variant_id: movementForm.value.product_variant_id,
            movement_type: movementForm.value.movement_type,
            quantity: toAsciiDigits(movementForm.value.quantity),
            reason: movementForm.value.reason,
            idempotency_key:
                movementForm.value.idempotency_key ||
                (window.crypto?.randomUUID?.() ?? `mv-${Date.now()}`),
        },
        {
            preserveState: true,
            onSuccess: () => {
                movementOpen.value = false;
                movementForm.value = {
                    product_variant_id: '',
                    movement_type: 'opening',
                    quantity: '',
                    reason: '',
                    idempotency_key: '',
                };
            },
            onError: (errors) => {
                movementErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                movementSending.value = false;
            },
        },
    );
};

const requiresReason = computed(() =>
    ['adjustment_in', 'adjustment_out'].includes(movementForm.value.movement_type),
);

const deltaPreview = computed(() => {
    if (movementForm.value.quantity === '') {
        return '';
    }

    const quantity = Number(toAsciiDigits(movementForm.value.quantity)) || 0;

    return ['adjustment_out'].includes(movementForm.value.movement_type) ? `−${quantity}` : `+${quantity}`;
});

const movementVariantLabel = (value: MovementTypeCode) => t(`inventory.movement.${value}`);

const deltaClass = (delta: number) => (delta >= 0 ? 'text-emerald-600' : 'text-red-600');

const stockPages = computed(() => Math.max(1, props.stock.last_page));
const movementPages = computed(() => Math.max(1, props.movements.last_page));
</script>

<template>
    <Head :title="t('inventory.title')" />

    <div class="space-y-6">
        <Heading :title="t('inventory.title')" :description="t('inventory.description')">
            <template #actions>
                <Button v-if="canManage" @click="movementOpen = true">
                    {{ t('inventory.recordMovement') }}
                </Button>
            </template>
        </Heading>

        <!-- Stock list (low stock first) -->
        <Card>
            <CardContent class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative w-full md:max-w-xs">
                        <Search class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" :placeholder="t('inventory.searchPlaceholder')" class="pr-9" />
                    </div>
                    <span class="text-xs text-muted-foreground">{{ t('inventory.lowStockFirst') }}</span>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('catalog.productName') }}</TableHead>
                            <TableHead>{{ t('catalog.variantName') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ t('catalog.sku') }}</TableHead>
                            <TableHead>{{ t('inventory.quantity') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="stock.data.length === 0" :colspan="4">
                            <div class="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                                <PackageSearch class="size-8" />
                                <span>{{ t('inventory.emptyStock') }}</span>
                            </div>
                        </TableEmpty>
                        <TableRow v-for="row in stock.data" :key="row.id">
                            <TableCell class="font-medium">{{ row.product_name }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ row.label }}</TableCell>
                            <TableCell class="hidden font-mono text-xs md:table-cell" dir="ltr">
                                {{ row.sku ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <Badge v-if="!row.tracks" variant="secondary">
                                    {{ t('inventory.notTracked') }}
                                </Badge>
                                <span v-else class="font-mono" :class="row.stock_on_hand > 0 ? '' : 'text-red-600'">
                                    {{ row.stock_on_hand.toLocaleString('fa-IR') }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="stockPages > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ stock.total.toLocaleString('fa-IR') }}</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="stock.current_page <= 1"
                            @click="router.get(inventoryIndex().url, { stock_page: String(stock.current_page - 1) }, { preserveState: true })"
                        >
                            ‹
                        </Button>
                        <span>
                            {{ stock.current_page.toLocaleString('fa-IR') }} /
                            {{ stockPages.toLocaleString('fa-IR') }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="stock.current_page >= stockPages"
                            @click="router.get(inventoryIndex().url, { stock_page: String(stock.current_page + 1) }, { preserveState: true })"
                        >
                            ›
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Movement history -->
        <Card>
            <CardContent class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <h3 class="text-base font-medium">{{ t('inventory.history') }}</h3>
                    <Select v-model="movementVariant">
                        <SelectTrigger class="w-full md:w-56">
                            <SelectValue :placeholder="t('inventory.allVariants')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('inventory.allVariants') }}</SelectItem>
                            <SelectItem v-for="variant in variantOptions" :key="variant.id" :value="variant.id">
                                {{ variant.product_name }} — {{ variant.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('inventory.movementType') }}</TableHead>
                            <TableHead>{{ t('catalog.productName') }}</TableHead>
                            <TableHead>{{ t('inventory.quantity') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ t('inventory.reason') }}</TableHead>
                            <TableHead class="hidden lg:table-cell">{{ t('orders.createdBy') }}</TableHead>
                            <TableHead>{{ t('customers.createdAt') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="movements.data.length === 0" :colspan="6">
                            <span class="py-4 text-muted-foreground">{{ t('inventory.emptyMovements') }}</span>
                        </TableEmpty>
                        <TableRow v-for="movement in movements.data" :key="movement.id">
                            <TableCell>
                                <Badge :variant="movement.quantity_delta >= 0 ? 'outline' : 'secondary'">
                                    {{ movementVariantLabel(movement.movement_type) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">{{ movement.product_name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ movement.variant_label }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="font-mono" :class="deltaClass(movement.quantity_delta)" dir="ltr">
                                {{ movement.quantity_delta > 0 ? '+' : '' }}{{ movement.quantity_delta }}
                            </TableCell>
                            <TableCell class="hidden max-w-48 truncate text-sm text-muted-foreground md:table-cell">
                                {{ movement.reason ?? '—' }}
                            </TableCell>
                            <TableCell class="hidden text-sm text-muted-foreground lg:table-cell">
                                {{ movement.actor_name ?? '—' }}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                {{ formatJalaliDateTime(movement.occurred_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="movementPages > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ movements.total.toLocaleString('fa-IR') }}</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="movements.current_page <= 1"
                            @click="router.get(inventoryIndex().url, { movement_page: String(movements.current_page - 1) }, { preserveState: true })"
                        >
                            ‹
                        </Button>
                        <span>
                            {{ movements.current_page.toLocaleString('fa-IR') }} /
                            {{ movementPages.toLocaleString('fa-IR') }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="movements.current_page >= movementPages"
                            @click="router.get(inventoryIndex().url, { movement_page: String(movements.current_page + 1) }, { preserveState: true })"
                        >
                            ›
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Record movement dialog -->
        <Dialog v-model:open="movementOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('inventory.recordMovement') }}</DialogTitle>
                    <DialogDescription>{{ t('inventory.description') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>{{ t('inventory.variant') }} *</Label>
                        <Select v-model="movementForm.product_variant_id">
                            <SelectTrigger><SelectValue :placeholder="t('inventory.selectVariant')" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="variant in variantOptions"
                                    :key="variant.id"
                                    :value="variant.id"
                                >
                                    {{ variant.product_name }} — {{ variant.label }}
                                    ({{ variant.stock_on_hand.toLocaleString('fa-IR') }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="movementErrors.product_variant_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label>{{ t('inventory.movementType') }} *</Label>
                            <Select v-model="movementForm.movement_type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in MANUAL_TYPES" :key="type" :value="type">
                                        {{ movementVariantLabel(type) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="movementErrors.movement_type" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('inventory.quantity') }} *</Label>
                            <div class="relative">
                                <Input
                                    v-model="movementForm.quantity"
                                    inputmode="numeric"
                                    dir="ltr"
                                    placeholder="۱۰"
                                />
                                <span
                                    v-if="deltaPreview"
                                    class="absolute top-1/2 left-2 -translate-y-1/2 font-mono text-xs text-muted-foreground"
                                >
                                    {{ deltaPreview }}
                                </span>
                            </div>
                            <InputError :message="movementErrors.quantity" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('inventory.reason') }} {{ requiresReason ? '*' : '' }}</Label>
                        <Textarea v-model="movementForm.reason" rows="2" :placeholder="t('inventory.reasonPlaceholder')" />
                        <InputError :message="movementErrors.reason" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="movementOpen = false">{{ t('common.cancel') }}</Button>
                    <Button :disabled="movementSending" @click="submitMovement">
                        <Spinner v-if="movementSending" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
