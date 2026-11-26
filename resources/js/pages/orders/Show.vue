<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Separator } from '@/components/ui/separator';
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
import { formatAmount, formatJalaliDateTime, toAsciiDigits } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    cancel as ordersCancel,
    index as ordersIndex,
    returnMethod as ordersReturn,
    status as ordersStatus,
} from '@/routes/orders';
import { refund as refundPayment, store as storePayment, voidMethod as voidPayment } from '@/routes/order-payments';
import type { OrderShowPayload, PaymentMethodCode } from '@/types';

interface Props {
    order: OrderShowPayload;
    movements: {
        id: string;
        variant_label: string | null;
        movement_type: string;
        quantity_delta: number;
        occurred_at: string;
    }[];
    canManage: boolean;
    canManagePayments: boolean;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        // Static only: defineOptions is hoisted outside setup(), so the
        // order number (props) cannot be referenced here. The page heading
        // carries the full identity instead.
        breadcrumbs: [{ title: t('orders.title'), href: ordersIndex().url }],
    },
});

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

// --- Status transitions ---------------------------------------------------------

const canCancel = computed(() => props.order.allowed_transitions.includes('cancelled'));
const canReturn = computed(() => props.order.allowed_transitions.includes('returned'));

const changeStatus = (status: string) => {
    router.patch(
        ordersStatus({ order: props.order.id }).url,
        { status },
        { preserveState: true },
    );
};

const cancelOpen = ref(false);
const cancelReason = ref('');

const submitCancel = () => {
    router.patch(
        ordersCancel({ order: props.order.id }).url,
        { reason: cancelReason.value },
        {
            preserveState: true,
            onSuccess: () => {
                cancelOpen.value = false;
            },
        },
    );
};

const returnOpen = ref(false);
const returnNote = ref('');

const submitReturn = () => {
    router.patch(
        ordersReturn({ order: props.order.id }).url,
        { note: returnNote.value },
        {
            preserveState: true,
            onSuccess: () => {
                returnOpen.value = false;
            },
        },
    );
};

// --- Payments -----------------------------------------------------------------------

const paymentOpen = ref(false);
const paymentForm = ref({ amount: '', method: 'card_to_card' as PaymentMethodCode, reference: '' });
const paymentErrors = ref<Record<string, string>>({});

const openPayment = () => {
    // Default to the remaining balance (spec 5.5: pay-in-full default).
    const paid = BigInt(props.order.paid_amount || '0');
    const total = BigInt(props.order.total_amount || '0');

    paymentForm.value.amount = (total > paid ? total - paid : total).toString();
    paymentOpen.value = true;
};

const submitPayment = () => {
    router.post(
        storePayment({ order: props.order.id }).url,
        {
            amount: toAsciiDigits(paymentForm.value.amount),
            method: paymentForm.value.method,
            reference_code: paymentForm.value.reference,
            idempotency_key: window.crypto?.randomUUID?.() ?? `pay-${Date.now()}`,
        },
        {
            preserveState: true,
            onSuccess: () => {
                paymentOpen.value = false;
                paymentErrors.value = {};
            },
            onError: (errors) => {
                paymentErrors.value = errors as Record<string, string>;
            },
        },
    );
};

const closePayment = (paymentId: string, action: 'refund' | 'void') => {
    const url =
        action === 'refund'
            ? refundPayment({ order: props.order.id, payment: paymentId }).url
            : voidPayment({ order: props.order.id, payment: paymentId }).url;

    router.patch(url, {}, { preserveState: true });
};

const attributeSummary = (attributes: Record<string, { code: string; label: string }> | null) => {
    if (!attributes) {
        return '';
    }

    return Object.values(attributes)
        .map((attribute) => attribute.label || attribute.code)
        .filter(Boolean)
        .join(' · ');
};
</script>

<template>
    <Head :title="order.order_number" />

    <div class="space-y-6">
        <Heading :title="order.order_number" :description="t('orders.description')">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Select v-if="canManage && order.allowed_transitions.filter((s) => !['cancelled', 'returned'].includes(s)).length > 0">
                        <SelectTrigger class="w-40">
                            <SelectValue :placeholder="t('orders.statusChange')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="target in order.allowed_transitions.filter(
                                    (item) => !['cancelled', 'returned'].includes(item),
                                )"
                                :key="target"
                                :value="target"
                                @click="changeStatus(target)"
                            >
                                {{ t(`orders.status.${target}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button v-if="canManage && canReturn" variant="outline" @click="returnOpen = true">
                        <Undo2 />
                        {{ t('orders.returnOrder') }}
                    </Button>

                    <Button v-if="canManage && canCancel" variant="destructive" @click="cancelOpen = true">
                        {{ t('orders.cancelOrder') }}
                    </Button>
                </div>
            </template>
        </Heading>

        <!-- Header summary -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="space-y-1 py-4">
                    <p class="text-xs text-muted-foreground">{{ t('common.status') }}</p>
                    <Badge :variant="statusVariant(order.status)">
                        {{ t(`orders.status.${order.status}`) }}
                    </Badge>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 py-4">
                    <p class="text-xs text-muted-foreground">{{ t('orders.paymentStatus') }}</p>
                    <Badge :variant="paymentVariant(order.payment_status)">
                        {{ t(`orders.payment.${order.payment_status}`) }}
                    </Badge>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 py-4">
                    <p class="text-xs text-muted-foreground">{{ t('orders.grandTotal') }}</p>
                    <p class="font-medium" dir="ltr">{{ formatAmount(order.total_amount, order.currency_code) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 py-4">
                    <p class="text-xs text-muted-foreground">{{ t('orders.placedAt') }}</p>
                    <p class="text-sm">{{ formatJalaliDateTime(order.placed_at) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Customer + meta -->
        <Card>
            <CardContent class="grid gap-4 py-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">{{ t('orders.customer') }}</p>
                    <Link
                        :href="`/customers/${order.customer.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ order.customer.display_name }}
                    </Link>
                    <p class="text-xs text-muted-foreground" dir="ltr">{{ order.customer.customer_number }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-xs text-muted-foreground">{{ t('orders.salesChannel') }}</p>
                    <p class="text-sm">
                        {{ t(`orders.channel.${order.sales_channel}`) }}
                        · {{ t(`orders.fulfillment.${order.fulfillment_method}`) }}
                    </p>
                    <p v-if="order.creator_name" class="text-xs text-muted-foreground">
                        {{ t('orders.createdBy') }}: {{ order.creator_name }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Items (snapshots, spec 5.5) -->
        <Card>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('catalog.productName') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ t('catalog.label') }}</TableHead>
                            <TableHead class="hidden lg:table-cell">{{ t('catalog.sku') }}</TableHead>
                            <TableHead>{{ t('inventory.quantity') }}</TableHead>
                            <TableHead>{{ t('orders.unitPrice') }}</TableHead>
                            <TableHead>{{ t('orders.lineDiscount') }}</TableHead>
                            <TableHead>{{ t('orders.total') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="order.items.length === 0" :colspan="7">
                            {{ t('orders.empty') }}
                        </TableEmpty>
                        <TableRow v-for="item in order.items" :key="item.id">
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ item.product_name }}</span>
                                    <span
                                        v-if="attributeSummary(item.attributes)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ attributeSummary(item.attributes) }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="hidden text-sm text-muted-foreground md:table-cell">
                                {{ item.variant_name ?? '—' }}
                            </TableCell>
                            <TableCell class="hidden font-mono text-xs lg:table-cell" dir="ltr">
                                {{ item.sku ?? '—' }}
                            </TableCell>
                            <TableCell>{{ item.quantity.toLocaleString('fa-IR') }}</TableCell>
                            <TableCell dir="ltr" class="whitespace-nowrap text-sm">
                                {{ formatAmount(item.unit_price, order.currency_code) }}
                            </TableCell>
                            <TableCell dir="ltr" class="whitespace-nowrap text-sm text-muted-foreground">
                                {{ formatAmount(item.discount_amount, order.currency_code) }}
                            </TableCell>
                            <TableCell dir="ltr" class="whitespace-nowrap font-medium">
                                {{ formatAmount(item.line_total, order.currency_code) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ t('orders.subtotal') }}</span>
                        <span dir="ltr">{{ formatAmount(order.subtotal_amount, order.currency_code) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ t('orders.orderDiscount') }}</span>
                        <span dir="ltr">− {{ formatAmount(order.discount_amount, order.currency_code) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ t('orders.shippingAmount') }}</span>
                        <span dir="ltr">+ {{ formatAmount(order.shipping_amount, order.currency_code) }}</span>
                    </div>
                    <Separator class="my-2" />
                    <div class="flex justify-between font-medium">
                        <span>{{ t('orders.grandTotal') }}</span>
                        <span dir="ltr">{{ formatAmount(order.total_amount, order.currency_code) }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Payments -->
            <Card>
                <CardContent class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium">{{ t('orders.paymentsHistory') }}</h3>
                        <Button
                            v-if="!['draft', 'cancelled'].includes(order.status)"
                            variant="outline"
                            size="sm"
                            @click="openPayment"
                        >
                            {{ t('orders.recordPayment') }}
                        </Button>
                    </div>

                    <div v-if="order.payments.length === 0" class="py-4 text-sm text-muted-foreground">
                        {{ t('orders.noPayments') }}
                    </div>

                    <div
                        v-for="payment in order.payments"
                        :key="payment.id"
                        class="flex items-center justify-between gap-2 rounded-lg border p-3"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium" dir="ltr">
                                {{ formatAmount(payment.amount, payment.currency_code) }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ t(`orders.method.${payment.method}`) }}
                                <template v-if="payment.reference_code"> · {{ payment.reference_code }}</template>
                                · {{ formatJalaliDateTime(payment.paid_at) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="
                                    payment.status === 'recorded' ? 'default' : payment.status === 'refunded' ? 'outline' : 'secondary'
                                "
                            >
                                {{ payment.status === 'recorded' ? t('common.approved') : payment.status === 'refunded' ? t('orders.payment.refunded') : t('common.revoked') }}
                            </Badge>
                            <template v-if="canManagePayments && payment.status === 'recorded'">
                                <Button variant="ghost" size="sm" @click="closePayment(payment.id, 'refund')">
                                    {{ t('orders.refundPayment') }}
                                </Button>
                                <Button variant="ghost" size="sm" @click="closePayment(payment.id, 'void')">
                                    {{ t('orders.voidPayment') }}
                                </Button>
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Stock movements of this order -->
            <Card>
                <CardContent class="space-y-3">
                    <h3 class="text-base font-medium">{{ t('orders.stockMovements') }}</h3>
                    <div v-if="movements.length === 0" class="py-4 text-sm text-muted-foreground">
                        {{ t('orders.noMovements') }}
                    </div>
                    <div
                        v-for="movement in movements"
                        :key="movement.id"
                        class="flex items-center justify-between rounded-lg border p-3 text-sm"
                    >
                        <div class="flex flex-col">
                            <span class="font-medium">{{ movement.variant_label ?? '—' }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ t(`inventory.movement.${movement.movement_type}`) }}
                                · {{ formatJalaliDateTime(movement.occurred_at) }}
                            </span>
                        </div>
                        <span
                            class="font-mono"
                            :class="movement.quantity_delta >= 0 ? 'text-emerald-600' : 'text-red-600'"
                            dir="ltr"
                        >
                            {{ movement.quantity_delta > 0 ? '+' : '' }}{{ movement.quantity_delta }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Address + notes -->
        <div v-if="order.shipping_address || order.customer_note || order.internal_note" class="grid gap-6 lg:grid-cols-2">
            <Card v-if="order.shipping_address">
                <CardContent class="space-y-2 py-4">
                    <h3 class="text-base font-medium">{{ t('orders.address') }}</h3>
                    <p class="text-sm">{{ order.shipping_address.address_line ?? '—' }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ order.shipping_address.province ?? '' }} {{ order.shipping_address.city ?? '' }}
                        <span v-if="order.shipping_address.postal_code" dir="ltr">
                            · {{ order.shipping_address.postal_code }}
                        </span>
                    </p>
                    <p v-if="order.shipping_address.recipient_name" class="text-sm">
                        {{ t('orders.recipientName') }}: {{ order.shipping_address.recipient_name }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="order.customer_note || order.internal_note || order.return_note">
                <CardContent class="space-y-2 py-4">
                    <h3 class="text-base font-medium">{{ t('orders.customerNote') }}</h3>
                    <p v-if="order.customer_note" class="text-sm">{{ order.customer_note }}</p>
                    <template v-if="order.internal_note">
                        <Separator />
                        <p class="text-xs text-muted-foreground">{{ t('orders.internalNote') }}</p>
                        <p class="text-sm">{{ order.internal_note }}</p>
                    </template>
                    <template v-if="order.return_note">
                        <Separator />
                        <p class="text-xs text-muted-foreground">{{ t('orders.returnNote') }}</p>
                        <p class="text-sm">{{ order.return_note }}</p>
                    </template>
                </CardContent>
            </Card>
        </div>

        <!-- Cancel dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('orders.cancelConfirmTitle') }}</DialogTitle>
                    <DialogDescription>{{ t('orders.cancelConfirmText') }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label>{{ t('orders.cancelReason') }}</Label>
                    <Textarea v-model="cancelReason" rows="2" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="cancelOpen = false">{{ t('common.cancel') }}</Button>
                    <Button variant="destructive" @click="submitCancel">{{ t('orders.cancelOrder') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Return dialog -->
        <Dialog v-model:open="returnOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('orders.returnConfirmTitle') }}</DialogTitle>
                    <DialogDescription>{{ t('orders.returnConfirmText') }}</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label>{{ t('orders.returnNote') }}</Label>
                    <Textarea v-model="returnNote" rows="2" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="returnOpen = false">{{ t('common.cancel') }}</Button>
                    <Button @click="submitReturn">{{ t('orders.returnOrder') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Payment dialog -->
        <Dialog v-model:open="paymentOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('orders.recordPayment') }}</DialogTitle>
                    <DialogDescription>{{ t('orders.paymentHint') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>{{ t('orders.paymentAmount') }} *</Label>
                        <Input v-model="paymentForm.amount" inputmode="numeric" dir="ltr" />
                        <InputError :message="paymentErrors.amount" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('orders.paymentMethod') }} *</Label>
                        <Select v-model="paymentForm.method">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">{{ t('orders.method.cash') }}</SelectItem>
                                <SelectItem value="card_to_card">{{ t('orders.method.card_to_card') }}</SelectItem>
                                <SelectItem value="pos">{{ t('orders.method.pos') }}</SelectItem>
                                <SelectItem value="online">{{ t('orders.method.online') }}</SelectItem>
                                <SelectItem value="bank_transfer">{{ t('orders.method.bank_transfer') }}</SelectItem>
                                <SelectItem value="other">{{ t('orders.method.other') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="paymentErrors.method" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('orders.payment.reference_code') }}</Label>
                        <Input v-model="paymentForm.reference" dir="ltr" />
                    </div>
                    <InputError :message="paymentErrors.payment" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="paymentOpen = false">{{ t('common.cancel') }}</Button>
                    <Button @click="submitPayment">{{ t('common.save') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
