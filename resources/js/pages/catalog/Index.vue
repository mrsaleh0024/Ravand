<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Boxes, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { toAsciiDigits } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    index as productsIndex,
    store as productsStore,
    update as productsUpdate,
    toggle as productsToggle,
} from '@/routes/products';
import { store as categoriesStore } from '@/routes/product-categories';
import type { CategoryItem, Paginated, ProductRow, VariantAttributes } from '@/types';

interface Props {
    products: Paginated<ProductRow>;
    categories: CategoryItem[];
    filters: { search: string; category: string; active: string };
    currency: string;
}

const props = defineProps<Props>();

const page = usePage();
const canManage = computed(() => page.props.auth.role === 'owner' || page.props.auth.role === 'manager');

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('catalog.title'), href: productsIndex().url }],
    },
});

// --- Filters ---------------------------------------------------------------

const search = ref(props.filters.search);
const categoryId = ref(props.filters.category || 'all');
const activeState = ref(props.filters.active || 'all');

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: value }), 350);
});

watch(categoryId, () => applyFilters());
watch(activeState, () => applyFilters());

const applyFilters = (patch: Record<string, string> = {}) => {
    router.get(
        productsIndex().url,
        {
            search: search.value,
            category: categoryId.value === 'all' ? '' : categoryId.value,
            active: activeState.value === 'all' ? '' : activeState.value,
            ...patch,
        },
        { preserveState: true, replace: true },
    );
};

const totalPages = computed(() => Math.max(1, props.products.last_page));

const goToPage = (pageNumber: number) => {
    if (pageNumber < 1 || pageNumber > totalPages.value) return;
    router.get(productsIndex().url, { page: String(pageNumber) }, { preserveState: true });
};

// --- Category dialog ---------------------------------------------------------

const categoryOpen = ref(false);
const categoryForm = ref({ name: '', parent_id: 'none', description: '' });
const categoryErrors = ref<Record<string, string>>({});

const submitCategory = () => {
    router.post(
        categoriesStore().url,
        {
            name: categoryForm.value.name,
            parent_id: categoryForm.value.parent_id === 'none' ? '' : categoryForm.value.parent_id,
            description: categoryForm.value.description,
        },
        {
            preserveState: true,
            onSuccess: () => {
                categoryOpen.value = false;
                categoryForm.value = { name: '', parent_id: 'none', description: '' };
                categoryErrors.value = {};
            },
            onError: (errors) => {
                categoryErrors.value = errors as Record<string, string>;
            },
        },
    );
};

// --- Product dialog ----------------------------------------------------------

type AttributeKey = keyof VariantAttributes;

type VariantDraft = {
    id: string | null;
    variant_name: string;
    sku: string;
    sale_price: string;
    cost_price: string;
    is_active: boolean;
    attributes: Partial<Record<AttributeKey, { code: string; label: string }>>;
};

const productOpen = ref(false);
const productEditingId = ref<string | null>(null);
const productForm = ref(emptyProduct());
const productErrors = ref<Record<string, string>>({});
const productSending = ref(false);

const ATTRIBUTE_FIELDS: { key: AttributeKey; label: string }[] = [
    { key: 'color', label: t('catalog.color') },
    { key: 'size', label: t('catalog.size') },
    { key: 'chador_qovare', label: t('catalog.chadorQovare') },
    { key: 'height', label: t('catalog.height') },
    { key: 'fabric', label: t('catalog.fabric') },
];

function emptyVariant(): VariantDraft {
    return { id: null, variant_name: '', sku: '', sale_price: '', cost_price: '', is_active: true, attributes: {} };
}

function emptyProduct() {
    return {
        name: '',
        category_id: 'none',
        brand: '',
        description: '',
        track_inventory: true,
        is_active: true,
        variants: [emptyVariant()],
    };
}

const openCreateProduct = () => {
    productEditingId.value = null;
    productForm.value = emptyProduct();
    productErrors.value = {};
    productOpen.value = true;
};

const openEditProduct = (product: ProductRow) => {
    productEditingId.value = product.id;
    productForm.value = {
        name: product.name,
        category_id: product.category_id ?? 'none',
        brand: product.brand ?? '',
        description: product.description ?? '',
        track_inventory: product.track_inventory,
        is_active: product.is_active,
        variants: product.variants.map((variant) => ({
            id: variant.id,
            variant_name: variant.variant_name ?? '',
            sku: variant.sku ?? '',
            sale_price: variant.sale_price,
            cost_price: variant.cost_price ?? '',
            is_active: variant.is_active,
            attributes: Object.fromEntries(
                Object.entries(variant.attributes ?? {}).map(([key, value]) => [
                    key,
                    { code: value?.code ?? '', label: value?.label ?? '' },
                ]),
            ) as VariantDraft['attributes'],
        })),
    };
    productErrors.value = {};
    productOpen.value = true;
};

const submitProduct = () => {
    productSending.value = true;
    productErrors.value = {};

    const payload = {
        name: productForm.value.name,
        category_id: productForm.value.category_id === 'none' ? '' : productForm.value.category_id,
        brand: productForm.value.brand,
        description: productForm.value.description,
        track_inventory: productForm.value.track_inventory,
        is_active: productForm.value.is_active,
        variants: productForm.value.variants.map((variant) => ({
            id: variant.id ?? '',
            variant_name: variant.variant_name,
            sku: toAsciiDigits(variant.sku),
            sale_price: toAsciiDigits(variant.sale_price),
            cost_price: toAsciiDigits(variant.cost_price),
            is_active: variant.is_active,
            attributes: Object.fromEntries(
                (Object.keys(variant.attributes) as AttributeKey[])
                    .filter((key) => {
                        const attribute = variant.attributes[key];

                        return attribute && (attribute.code !== '' || attribute.label !== '');
                    })
                    .map((key) => [
                        key,
                        {
                            code: toAsciiDigits(variant.attributes[key]?.code ?? ''),
                            label: variant.attributes[key]?.label ?? '',
                        },
                    ]),
            ),
        })),
    };

    router[productEditingId.value === null ? 'post' : 'put'](
        (productEditingId.value === null
            ? productsStore()
            : productsUpdate({ product: productEditingId.value })
        ).url,
        payload,
        {
            preserveState: true,
            onSuccess: () => {
                productOpen.value = false;
            },
            onError: (errors) => {
                productErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                productSending.value = false;
            },
        },
    );
};

const toggleProduct = (product: ProductRow) => {
    router.patch(productsToggle({ product: product.id }).url, {}, { preserveState: true });
};

const categoryName = (id: string | null) =>
    props.categories.find((category) => category.id === id)?.name ?? t('catalog.noCategory');
</script>

<template>
    <Head :title="t('catalog.title')" />

    <div class="space-y-6">
        <Heading :title="t('catalog.title')" :description="t('catalog.description')">
            <template #actions>
                <Button v-if="canManage" variant="outline" @click="categoryOpen = true">
                    {{ t('catalog.createCategory') }}
                </Button>
                <Button v-if="canManage" @click="openCreateProduct">
                    <Plus />
                    {{ t('catalog.create') }}
                </Button>
            </template>
        </Heading>

        <!-- Filters -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <div class="relative w-full md:max-w-xs">
                <Search class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="search" :placeholder="t('catalog.searchPlaceholder')" class="pr-9" />
            </div>

            <Select v-model="categoryId">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue :placeholder="t('catalog.category')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ t('catalog.allCategories') }}</SelectItem>
                    <SelectItem v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="activeState">
                <SelectTrigger class="w-full md:w-36">
                    <SelectValue :placeholder="t('common.status')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ t('catalog.allStates') }}</SelectItem>
                    <SelectItem value="active">{{ t('common.active') }}</SelectItem>
                    <SelectItem value="inactive">{{ t('common.no') }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Products -->
        <Card>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('catalog.productName') }}</TableHead>
                            <TableHead class="hidden md:table-cell">{{ t('catalog.category') }}</TableHead>
                            <TableHead>{{ t('catalog.variants') }}</TableHead>
                            <TableHead>{{ t('catalog.stock') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">{{ t('common.status') }}</TableHead>
                            <TableHead v-if="canManage" class="w-40">
                                {{ t('common.actions') }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="products.data.length === 0" :colspan="canManage ? 6 : 5">
                            <div class="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                                <Boxes class="size-8" />
                                <span>{{ t('catalog.empty') }}</span>
                                <span class="text-xs">{{ t('catalog.emptyHint') }}</span>
                            </div>
                        </TableEmpty>
                        <TableRow v-for="product in products.data" :key="product.id">
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ product.name }}</span>
                                    <span v-if="product.brand" class="text-xs text-muted-foreground">
                                        {{ product.brand }}
                                    </span>
                                    <span v-if="product.min_price" class="text-xs" dir="ltr">
                                        {{ t('catalog.minPrice') }} {{ product.min_price }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="hidden text-sm text-muted-foreground md:table-cell">
                                {{ categoryName(product.category_id) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="variant in product.variants"
                                        :key="variant.id"
                                        :variant="variant.is_active ? 'outline' : 'secondary'"
                                        class="text-xs"
                                    >
                                        {{ variant.variant_name || variant.sku || '—' }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span v-if="product.track_inventory" class="font-mono text-xs" dir="ltr">
                                    {{ product.variants.map((variant) => variant.stock_on_hand).join(' / ') }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground">
                                    {{ t('inventory.notTracked') }}
                                </span>
                            </TableCell>
                            <TableCell class="hidden sm:table-cell">
                                <Badge :variant="product.is_active ? 'default' : 'secondary'">
                                    {{ product.is_active ? t('common.active') : t('common.no') }}
                                </Badge>
                            </TableCell>
                            <TableCell v-if="canManage">
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="sm" @click="openEditProduct(product)">
                                        {{ t('catalog.editProduct') }}
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="toggleProduct(product)">
                                        {{ product.is_active ? t('catalog.deactivateProduct') : t('catalog.activateProduct') }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ products.total.toLocaleString('fa-IR') }}</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="products.current_page <= 1"
                            @click="goToPage(products.current_page - 1)"
                        >
                            ‹
                        </Button>
                        <span>
                            {{ products.current_page.toLocaleString('fa-IR') }} /
                            {{ totalPages.toLocaleString('fa-IR') }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="products.current_page >= totalPages"
                            @click="goToPage(products.current_page + 1)"
                        >
                            ›
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Category dialog -->
        <Dialog v-model:open="categoryOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('catalog.createCategory') }}</DialogTitle>
                    <DialogDescription>{{ t('catalog.categories') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>{{ t('catalog.categoryName') }}</Label>
                        <Input v-model="categoryForm.name" />
                        <InputError :message="categoryErrors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('catalog.parentCategory') }}</Label>
                        <Select v-model="categoryForm.parent_id">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{{ t('catalog.noParent') }}</SelectItem>
                                <SelectItem v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="categoryErrors.parent_id" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('catalog.productDescription') }}</Label>
                        <Textarea v-model="categoryForm.description" rows="2" />
                        <InputError :message="categoryErrors.description" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="categoryOpen = false">{{ t('common.cancel') }}</Button>
                    <Button @click="submitCategory">{{ t('common.save') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Product dialog -->
        <Dialog v-model:open="productOpen">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ productEditingId === null ? t('catalog.newProduct') : t('catalog.editProduct') }}
                    </DialogTitle>
                    <DialogDescription>{{ t('orders.snapshotNote') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>{{ t('catalog.productName') }} *</Label>
                            <Input v-model="productForm.name" />
                            <InputError :message="productErrors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('catalog.category') }}</Label>
                            <Select v-model="productForm.category_id">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{{ t('catalog.noCategory') }}</SelectItem>
                                    <SelectItem v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="productErrors.category_id" />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('catalog.brand') }}</Label>
                            <Input v-model="productForm.brand" />
                            <InputError :message="productErrors.brand" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox v-model:model-value="productForm.track_inventory" />
                                {{ t('catalog.trackInventory') }}
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('catalog.productDescription') }}</Label>
                        <Textarea v-model="productForm.description" rows="2" />
                        <InputError :message="productErrors.description" />
                    </div>

                    <!-- Variants with structured attribute fields (spec 5.3) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>{{ t('catalog.variants') }} *</Label>
                            <Button variant="outline" size="sm" @click="productForm.variants.push(emptyVariant())">
                                <Plus />
                                {{ t('catalog.addVariant') }}
                            </Button>
                        </div>
                        <InputError :message="productErrors.variants" />

                        <Card v-for="(variant, index) in productForm.variants" :key="index">
                            <CardContent class="space-y-3 pt-4">
                                <div class="grid gap-3 md:grid-cols-4">
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">{{ t('catalog.variantName') }}</Label>
                                        <Input v-model="variant.variant_name" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">{{ t('catalog.sku') }}</Label>
                                        <Input v-model="variant.sku" dir="ltr" placeholder="SKU-01" />
                                        <InputError :message="productErrors[`variants.${index}.sku`]" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">{{ t('catalog.salePrice') }} *</Label>
                                        <Input
                                            v-model="variant.sale_price"
                                            inputmode="numeric"
                                            dir="ltr"
                                            placeholder="۱۵۰۰۰۰"
                                        />
                                        <InputError :message="productErrors[`variants.${index}.sale_price`]" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">{{ t('catalog.costPrice') }}</Label>
                                        <Input
                                            v-model="variant.cost_price"
                                            inputmode="numeric"
                                            dir="ltr"
                                            placeholder="۱۲۰۰۰۰"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-5">
                                    <div v-for="field in ATTRIBUTE_FIELDS" :key="field.key" class="space-y-1.5">
                                        <Label class="text-xs">{{ field.label }}</Label>
                                        <div class="flex gap-1">
                                            <Input
                                                :model-value="variant.attributes[field.key]?.label ?? ''"
                                                class="w-2/3"
                                                @update:model-value="
                                                    (value: string | number) =>
                                                        (variant.attributes[field.key] = {
                                                            code: variant.attributes[field.key]?.code ?? '',
                                                            label: String(value),
                                                        })
                                                "
                                            />
                                            <Input
                                                :model-value="variant.attributes[field.key]?.code ?? ''"
                                                dir="ltr"
                                                class="w-1/3"
                                                @update:model-value="
                                                    (value: string | number) =>
                                                        (variant.attributes[field.key] = {
                                                            code: String(value),
                                                            label: variant.attributes[field.key]?.label ?? '',
                                                        })
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox v-model:model-value="variant.is_active" />
                                        {{ t('common.active') }}
                                    </label>
                                    <Button
                                        v-if="productForm.variants.length > 1"
                                        variant="ghost"
                                        size="sm"
                                        @click="productForm.variants.splice(index, 1)"
                                    >
                                        {{ t('common.cancel') }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="productOpen = false">{{ t('common.cancel') }}</Button>
                    <Button :disabled="productSending" @click="submitProduct">
                        <Spinner v-if="productSending" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
