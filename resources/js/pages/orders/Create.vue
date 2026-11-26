<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Trash2, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { formatAmount, toAsciiDigits } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { AcceptableValue } from 'reka-ui';
import { index as ordersIndex, store as ordersStore } from '@/routes/orders';
import { lookup as customersLookup } from '@/routes/customers';
import type {
    CustomerLookupMatch,
    FulfillmentMethodCode,
    OrderFormProduct,
    PaymentMethodCode,
    SalesChannelCode,
} from '@/types';

interface Props {
    products: OrderFormProduct[];
    currency: string;
    channels: string[];
    preselectedCustomer: CustomerLookupMatch | null;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('orders.title'), href: ordersIndex().url },
            { title: t('orders.create'), href: ordersStore().url },
        ],
    },
});

// --- Customer picker -----------------------------------------------------------

const customerQuery = ref('');
const customerMatches = ref<CustomerLookupMatch[]>([]);
const customerSearching = ref(false);
const selectedCustomer = ref<CustomerLookupMatch | null>(props.preselectedCustomer);

let customerTimer: ReturnType<typeof setTimeout> | null = null;

watch(customerQuery, (value) => {
    if (customerTimer) clearTimeout(customerTimer);

    if (value.trim() === '') {
        customerMatches.value = [];

        return;
    }

    customerTimer = setTimeout(async () => {
        customerSearching.value = true;

        try {
            const response = await fetch(`${customersLookup().url}?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = (await response.json()) as { matches: CustomerLookupMatch[] };
            customerMatches.value = data.matches;
        } catch {
            customerMatches.value = [];
        } finally {
            customerSearching.value = false;
        }
    }, 300);
});

const pickCustomer = (match: CustomerLookupMatch) => {
    selectedCustomer.value = match;
    customerQuery.value = '';
    customerMatches.value = [];

    const defaultAddress = match.addresses.find((address) => address.is_default) ?? match.addresses[0];

    if (defaultAddress) {
        address.value.recipient_name = defaultAddress.recipient_name ?? match.display_name;
        address.value.province = defaultAddress.province ?? '';
        address.value.city = defaultAddress.city ?? '';
        address.value.address_line = defaultAddress.address_line ?? '';
        address.value.postal_code = defaultAddress.postal_code ?? '';
    }
};

// --- Items -----------------------------------------------------------------------

type LineDraft = {
    product_id: string;
    variant_id: string;
    quantity: string;
    discount_amount: string;
};

const lines = ref<LineDraft[]>([{ product_id: '', variant_id: '', quantity: '1', discount_amount: '' }]);

const addLine = () => {
    lines.value.push({ product_id: '', variant_id: '', quantity: '1', discount_amount: '' });
};

const removeLine = (index: number) => {
    lines.value.splice(index, 1);
};

const variantsOf = (line: LineDraft) =>
    props.products.find((product) => product.id === line.product_id)?.variants ?? [];

const variantOf = (line: LineDraft) => variantsOf(line).find((variant) => variant.id === line.variant_id);

const productOf = (line: LineDraft) => props.products.find((product) => product.id === line.product_id);

const onProductChange = (line: LineDraft) => {
    line.variant_id = '';
};

/** Integer amount string → BigInt (0n for garbage); display math only —
 *  the server recomputes every total from raw data (spec 5.5). */
const amountOf = (value: string | null | undefined): bigint => {
    const digits = toAsciiDigits(String(value ?? '')).replace(/[^0-9]/g, '');

    try {
        return digits === '' ? 0n : BigInt(digits);
    } catch {
        return 0n;
    }
};

const lineTotal = (line: LineDraft): bigint => {
    const variant = variantOf(line);

    if (!variant) {
        return 0n;
    }

    const quantity = Number(toAsciiDigits(line.quantity)) || 0;
    const gross = amountOf(variant.sale_price) * BigInt(Math.max(0, quantity));
    const discount = amountOf(line.discount_amount);

    return gross > discount ? gross - discount : 0n;
};

const subtotal = computed(() => lines.value.reduce((sum, line) => sum + lineTotal(line), 0n));

const orderDiscount = ref('');
const shippingAmount = ref('');

const grandTotal = computed(() => {
    const discount = amountOf(orderDiscount.value);
    const shipping = amountOf(shippingAmount.value);
    const base = subtotal.value > discount ? subtotal.value - discount : 0n;

    return base + shipping;
});

// --- Order fields -------------------------------------------------------------------

const salesChannel = ref<SalesChannelCode>('instagram');
const fulfillment = ref<FulfillmentMethodCode>('in_person');
const customerNote = ref('');
const internalNote = ref('');

const address = ref({
    recipient_name: '',
    phone: '',
    province: '',
    city: '',
    address_line: '',
    postal_code: '',
});

watch(fulfillment, (value) => {
    if (value === 'shipping' && !address.value.phone && selectedCustomer.value?.phone) {
        address.value.phone = selectedCustomer.value.phone;
    }
});

// --- Payment -------------------------------------------------------------------------

const payInFull = ref(true);
const paymentAmount = ref('');
const paymentMethod = ref<PaymentMethodCode>('card_to_card');
const paymentReference = ref('');

watch([payInFull, grandTotal], ([enabled, total]) => {
    if (enabled) {
        paymentAmount.value = total.toString();
    }
});

// --- Submission --------------------------------------------------------------------------

/** One key per form session: a retry after a network failure reuses it, so the
 *  server collapses double-submits into a single order (spec 5.5). */
const idempotencyKey = window.crypto?.randomUUID?.() ?? `ord-${Date.now()}`;
const paymentIdempotencyKey = window.crypto?.randomUUID?.() ?? `pay-${Date.now()}`;

const errors = ref<Record<string, string>>({});
const sending = ref(false);

const buildPayload = (status: 'draft' | 'confirmed') => ({
    idempotency_key: idempotencyKey,
    customer_id: selectedCustomer.value?.id ?? '',
    items: lines.value
        .filter((line) => line.variant_id !== '')
        .map((line) => ({
            variant_id: line.variant_id,
            quantity: toAsciiDigits(line.quantity),
            discount_amount: toAsciiDigits(line.discount_amount),
        })),
    sales_channel: salesChannel.value,
    fulfillment_method: fulfillment.value,
    status,
    discount_amount: toAsciiDigits(orderDiscount.value),
    shipping_amount: toAsciiDigits(shippingAmount.value),
    customer_note: customerNote.value,
    internal_note: internalNote.value,
    shipping_address: fulfillment.value === 'shipping' ? address.value : null,
    payment:
        status === 'confirmed' && payInFull.value
            ? {
                  amount: toAsciiDigits(paymentAmount.value),
                  method: paymentMethod.value,
                  reference_code: paymentReference.value,
                  idempotency_key: paymentIdempotencyKey,
              }
            : null,
});

const submit = (status: 'draft' | 'confirmed') => {
    sending.value = true;
    errors.value = {};

    router.post(ordersStore().url, buildPayload(status), {
        onError: (serverErrors) => {
            errors.value = serverErrors as Record<string, string>;
        },
        onFinish: () => {
            sending.value = false;
        },
    });
};

const currencyLabel = (code: string) => (code === 'IRT' ? 'تومان' : 'ریال');
</script>

<template>
    <Head :title="t('orders.create')" />

    <div class="space-y-6">
        <Heading :title="t('orders.create')" :description="t('orders.description')" />

        <!-- Customer -->
        <Card>
            <CardContent class="space-y-3">
                <div v-if="!selectedCustomer" class="space-y-3">
                    <Label>{{ t('orders.customer') }} *</Label>
                    <div class="relative">
                        <Input
                            v-model="customerQuery"
                            type="search"
                            :placeholder="t('orders.customerSearchPlaceholder')"
                        />
                        <Spinner v-if="customerSearching" class="absolute top-1/2 left-2 size-4 -translate-y-1/2" />
                    </div>

                    <div v-if="customerMatches.length > 0" class="space-y-1">
                        <button
                            v-for="match in customerMatches"
                            :key="match.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border p-3 text-right transition-colors hover:bg-accent"
                            @click="pickCustomer(match)"
                        >
                            <span class="flex flex-col">
                                <span class="font-medium">{{ match.display_name }}</span>
                                <span class="text-xs text-muted-foreground" dir="ltr">
                                    {{ match.customer_number }}
                                </span>
                            </span>
                            <span class="text-sm text-muted-foreground" dir="ltr">
                                {{ match.phone ?? match.city ?? '' }}
                            </span>
                        </button>
                    </div>
                    <p v-else-if="customerQuery.length > 1" class="text-sm text-muted-foreground">
                        {{ t('orders.searchEmpty') }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ t('orders.newCustomerHint') }}
                        <Link href="/customers" class="underline">{{ t('nav.customers') }}</Link>
                        — {{ t('orders.newCustomerLink') }}
                    </p>
                </div>

                <div v-else class="flex items-center justify-between gap-3">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ selectedCustomer.display_name }}</span>
                        <span class="text-xs text-muted-foreground" dir="ltr">
                            {{ selectedCustomer.customer_number }}
                            <template v-if="selectedCustomer.phone"> · {{ selectedCustomer.phone }}</template>
                        </span>
                    </div>
                    <Button variant="outline" size="sm" @click="selectedCustomer = null">
                        {{ t('orders.changeCustomer') }}
                    </Button>
                </div>
                <InputError :message="errors.customer_id" />
            </CardContent>
        </Card>

        <!-- Items -->
        <Card>
            <CardContent class="space-y-4">
                <div class="flex items-center justify-between">
                    <Label>{{ t('orders.items') }} *</Label>
                    <Button variant="outline" size="sm" @click="addLine">
                        <Plus />
                        {{ t('orders.addItem') }}
                    </Button>
                </div>
                <InputError :message="errors.items" />
                <InputError :message="errors.order" />

                <div
                    v-for="(line, index) in lines"
                    :key="index"
                    class="space-y-3 rounded-lg border p-3"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.selectProduct') }}</Label>
                            <Select
                                :model-value="line.product_id"
                                @update:model-value="
                                    (value: AcceptableValue) => {
                                        line.product_id = String(value);
                                        onProductChange(line);
                                    }
                                "
                            >
                                <SelectTrigger><SelectValue :placeholder="t('orders.selectProduct')" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.selectVariant') }}</Label>
                            <Select v-model="line.variant_id" :disabled="line.product_id === ''">
                                <SelectTrigger><SelectValue :placeholder="t('orders.selectVariant')" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="variant in variantsOf(line)" :key="variant.id" :value="variant.id">
                                        {{ variant.label }}
                                        <span class="text-xs text-muted-foreground" dir="ltr">
                                            {{ variant.sale_price }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors[`items.${index}.variant_id`]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('inventory.quantity') }}</Label>
                            <Input v-model="line.quantity" inputmode="numeric" dir="ltr" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.lineDiscount') }}</Label>
                            <Input
                                v-model="line.discount_amount"
                                inputmode="numeric"
                                dir="ltr"
                                placeholder="۰"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.unitPrice') }}</Label>
                            <div class="flex h-9 items-center text-sm text-muted-foreground">
                                <template v-if="variantOf(line)">
                                    {{ variantOf(line)?.sale_price }}
                                    <span class="text-xs"> {{ currencyLabel(variantOf(line)?.currency_code ?? '') }}</span>
                                </template>
                                <template v-else>—</template>
                            </div>
                        </div>
                    </div>

                    <!-- Stock hint -->
                    <p
                        v-if="variantOf(line) && productOf(line)?.track_inventory"
                        class="text-xs"
                        :class="
                            (Number(variantOf(line)?.stock_on_hand ?? 0) < Number(toAsciiDigits(line.quantity) || 0))
                                ? 'text-red-600'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ t('inventory.stock') }}:
                        {{ (variantOf(line)?.stock_on_hand ?? 0).toLocaleString('fa-IR') }}
                        <span v-if="Number(variantOf(line)?.stock_on_hand ?? 0) < Number(toAsciiDigits(line.quantity) || 0)">
                            — {{ t('orders.outOfStockHint') }}
                        </span>
                    </p>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium" dir="ltr">
                            {{ formatAmount(lineTotal(line).toString(), currency) }}
                        </span>
                        <Button
                            v-if="lines.length > 1"
                            variant="ghost"
                            size="sm"
                            @click="removeLine(index)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>

                <!-- Totals preview (server recomputes — spec 5.5) -->
                <div class="space-y-2 rounded-lg bg-muted/50 p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">{{ t('orders.subtotal') }}</span>
                        <span dir="ltr">{{ formatAmount(subtotal.toString(), currency) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.orderDiscount') }}</Label>
                            <Input
                                v-model="orderDiscount"
                                inputmode="numeric"
                                dir="ltr"
                                placeholder="۰"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.shippingAmount') }}</Label>
                            <Input
                                v-model="shippingAmount"
                                inputmode="numeric"
                                dir="ltr"
                                placeholder="۰"
                            />
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t pt-2 font-medium">
                        <span>{{ t('orders.grandTotal') }}</span>
                        <span dir="ltr">{{ formatAmount(grandTotal.toString(), currency) }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Channel / fulfillment / address -->
        <Card>
            <CardContent class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>{{ t('orders.salesChannel') }} *</Label>
                        <Select v-model="salesChannel">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="item in channels" :key="item" :value="item">
                                    {{ t(`orders.channel.${item}`) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.sales_channel" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>{{ t('orders.fulfillment') }} *</Label>
                        <Select v-model="fulfillment">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="shipping">{{ t('orders.fulfillment.shipping') }}</SelectItem>
                                <SelectItem value="pickup">{{ t('orders.fulfillment.pickup') }}</SelectItem>
                                <SelectItem value="in_person">{{ t('orders.fulfillment.in_person') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.fulfillment_method" />
                    </div>
                </div>

                <!-- Shipping address (spec 5.5: snapshot at sale time) -->
                <div v-if="fulfillment === 'shipping'" class="space-y-3 rounded-lg border p-3">
                    <div class="flex items-center justify-between">
                        <Label class="text-sm">{{ t('orders.address') }}</Label>
                        <span class="text-xs text-muted-foreground">{{ t('orders.addressHint') }}</span>
                    </div>
                    <InputError :message="errors['shipping_address.address_line']" />

                    <div v-if="selectedCustomer && selectedCustomer.addresses.length > 0" class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.addressTemplate') }}</Label>
                        <Select
                            @update:model-value="
                                (value: AcceptableValue) => {
                                    const saved = selectedCustomer?.addresses.find((item) => item.id === String(value));

                                    if (saved && selectedCustomer) {
                                        address.recipient_name = saved.recipient_name ?? selectedCustomer.display_name;
                                        address.province = saved.province ?? '';
                                        address.city = saved.city ?? '';
                                        address.address_line = saved.address_line ?? '';
                                        address.postal_code = saved.postal_code ?? '';
                                    }
                                }
                            "
                        >
                            <SelectTrigger><SelectValue :placeholder="t('orders.addressTemplate')" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="saved in selectedCustomer.addresses"
                                    :key="saved.id"
                                    :value="saved.id"
                                >
                                    {{ saved.title }}
                                    <template v-if="saved.city"> — {{ saved.city }}</template>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedCustomer.addresses.every((item) => !item.address_line)" class="text-xs text-muted-foreground">
                            {{ t('orders.addressHint') }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.recipientName') }}</Label>
                            <Input v-model="address.recipient_name" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('common.phone') }}</Label>
                            <Input v-model="address.phone" dir="ltr" inputmode="tel" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.province') }}</Label>
                            <Input v-model="address.province" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.city') }}</Label>
                            <Input v-model="address.city" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="space-y-1.5 sm:col-span-3">
                            <Label class="text-xs">{{ t('orders.addressLine') }} *</Label>
                            <Textarea v-model="address.address_line" rows="2" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">{{ t('orders.postalCode') }}</Label>
                            <Input v-model="address.postal_code" dir="ltr" inputmode="numeric" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.customerNote') }}</Label>
                        <Textarea v-model="customerNote" rows="2" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.internalNote') }}</Label>
                        <Textarea v-model="internalNote" rows="2" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Payment (default: pay in full, spec 5.5) -->
        <Card>
            <CardContent class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <Label>{{ t('orders.payment') }}</Label>
                        <p class="text-xs text-muted-foreground">{{ t('orders.paymentHint') }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model:model-value="payInFull" />
                        {{ t('orders.payInFull') }}
                    </label>
                </div>

                <div v-if="payInFull" class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.paymentAmount') }}</Label>
                        <Input v-model="paymentAmount" inputmode="numeric" dir="ltr" />
                        <InputError :message="errors['payment.amount']" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.paymentMethod') }}</Label>
                        <Select v-model="paymentMethod">
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
                        <InputError :message="errors['payment.method']" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('orders.payment.reference_code') }}</Label>
                        <Input v-model="paymentReference" dir="ltr" />
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">{{ t('orders.noPaymentNow') }}</p>
                <InputError :message="errors.payment" />
            </CardContent>
        </Card>

        <!-- Actions: sticky bar for mobile (spec 6.7) -->
        <div class="sticky bottom-4 z-10 flex flex-col-reverse gap-2 rounded-lg border bg-background p-3 shadow-lg sm:flex-row sm:justify-end">
            <p class="flex-1 self-center text-xs text-muted-foreground sm:hidden">
                {{ t('orders.snapshotNote') }}
            </p>
            <Button variant="outline" :disabled="sending || !selectedCustomer" @click="submit('draft')">
                {{ t('orders.saveDraft') }}
            </Button>
            <Button :disabled="sending || !selectedCustomer" @click="submit('confirmed')">
                <Spinner v-if="sending" />
                {{ t('orders.submit') }}
                <span dir="ltr" class="font-normal">
                    {{ formatAmount(grandTotal.toString(), currency) }}
                </span>
            </Button>
        </div>
    </div>
</template>
