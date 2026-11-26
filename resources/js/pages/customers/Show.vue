<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import CustomerAddressController from '@/actions/App/Http/Controllers/Customers/CustomerAddressController';
import CustomerConsentController from '@/actions/App/Http/Controllers/Customers/CustomerConsentController';
import CustomerContactController from '@/actions/App/Http/Controllers/Customers/CustomerContactController';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import CustomerInteractionController from '@/actions/App/Http/Controllers/Customers/CustomerInteractionController';
import CustomerMeasurementController from '@/actions/App/Http/Controllers/Customers/CustomerMeasurementController';
import CustomerNoteController from '@/actions/App/Http/Controllers/Customers/CustomerNoteController';
import CustomerPreferenceController from '@/actions/App/Http/Controllers/Customers/CustomerPreferenceController';
import CustomerSocialAccountController from '@/actions/App/Http/Controllers/Customers/CustomerSocialAccountController';
import CustomerTagController from '@/actions/App/Http/Controllers/Customers/CustomerTagController';
import TagController from '@/actions/App/Http/Controllers/Customers/TagController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { formatJalaliDate, formatJalaliDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { archive, restore, show as customersShow } from '@/routes/customers';
import { index as customersIndex } from '@/routes/customers';
import {
    attach as attachTag,
    detach as detachTag,
} from '@/routes/customer-tags';
import { store as createTag } from '@/routes/tags';
import type {
    CustomerInteractionItem,
    CustomerNoteItem,
    CustomerProfile,
    TagItem,
} from '@/types';

interface Props {
    customer: CustomerProfile;
    notes: CustomerNoteItem[];
    interactions: CustomerInteractionItem[];
    isPrivileged: boolean;
    tags: TagItem[];
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            // Static only: defineOptions is hoisted outside setup(), so the
            // customer name/id (props) cannot be referenced here. The page
            // heading carries the full identity instead.
            { title: t('customers.title'), href: customersIndex() },
        ],
    },
});

const isArchived = computed(() => props.customer.status === 'archived');

// One shared dialog for every "add record" form on the profile.
type DialogKind =
    | 'contact'
    | 'social'
    | 'address'
    | 'note'
    | 'interaction'
    | 'consent'
    | 'preference'
    | 'measurement'
    | null;

const dialog = ref<DialogKind>(null);

const dialogMeta: Record<Exclude<DialogKind, null>, { title: string; description: string }> = {
    contact: { title: t('customers.contact.add'), description: '' },
    social: { title: t('customers.social.add'), description: '' },
    address: { title: t('customers.address.add'), description: '' },
    note: { title: t('customers.note.add'), description: '' },
    interaction: { title: t('customers.interaction.add'), description: '' },
    consent: { title: t('customers.consent.add'), description: '' },
    preference: { title: t('customers.preference.add'), description: '' },
    measurement: { title: t('customers.measurement.add'), description: '' },
};

// Tag management
const attachTagId = ref('');
const newTagName = ref('');

const statusVariant = (value: string) =>
    (
        {
            active: 'default',
            inactive: 'secondary',
            blocked: 'destructive',
            archived: 'outline',
        } as const
    )[value] ?? 'secondary';

const consentVariant = (value: string) =>
    (
        {
            granted: 'default',
            denied: 'destructive',
            withdrawn: 'secondary',
            unknown: 'outline',
        } as const
    )[value] ?? 'outline';

const levelVariant = (value: string | null) =>
    (
        {
            like: 'default',
            dislike: 'destructive',
            neutral: 'secondary',
        } as const
    )[value ?? 'neutral'] ?? 'secondary';

const channelLabel = (value: string) => t(`customers.channel.${value}`);
const contactTypeLabel = (value: string) => t(`customers.contact.type.${value}`);
const socialLabel = (value: string) => t(`customers.social.platform.${value}`);
</script>

<template>
    <Head :title="`${customer.display_name} — ${t('customers.profile')}`" />

    <div class="space-y-6">
        <Heading
            :title="customer.display_name"
            :description="`${t('customers.number')}: ${customer.customer_number}`"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="statusVariant(customer.status)">
                        {{ t(`customers.status.${customer.status}`) }}
                    </Badge>
                    <Button
                        v-if="!isArchived && isPrivileged"
                        variant="outline"
                        @click="router.patch(archive({ customer: customer.id }).url)"
                    >
                        <Archive />
                        {{ t('customers.archive') }}
                    </Button>
                    <Button
                        v-if="isArchived"
                        variant="outline"
                        @click="router.patch(restore({ customer: customer.id }).url)"
                    >
                        <ArchiveRestore />
                        {{ t('customers.restore') }}
                    </Button>
                </div>
            </template>
        </Heading>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">{{ t('customers.phone') }}</p>
                    <p dir="ltr" class="mt-1 text-sm font-medium text-right">
                        {{ customer.contacts.find((c) => c.is_primary)?.value
                            ?? customer.contacts[0]?.value ?? '—' }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">{{ t('customers.city') }}</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ customer.addresses[0]?.city ?? '—' }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">{{ t('customers.channel') }}</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ channelLabel(customer.acquisition_channel) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">{{ t('customers.createdAt') }}</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ formatJalaliDate(customer.created_at) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Tabs default-value="overview" class="gap-4">
            <TabsList class="w-full max-w-xl">
                <TabsTrigger value="overview">{{ t('customers.tab.overview') }}</TabsTrigger>
                <TabsTrigger value="contacts">{{ t('customers.tab.contacts') }}</TabsTrigger>
                <TabsTrigger value="interests">{{ t('customers.tab.interests') }}</TabsTrigger>
                <TabsTrigger value="notes">{{ t('customers.tab.notes') }}</TabsTrigger>
                <TabsTrigger value="interactions">
                    {{ t('customers.tab.interactions') }}
                </TabsTrigger>
            </TabsList>

            <!-- ================= Overview: base info + tags ================= -->
            <TabsContent value="overview" class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('customers.basicInfo') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="CustomerController.update.form({ customer: customer.id })"
                            v-slot="{ errors, processing }"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label for="display_name">
                                    {{ t('customers.displayName') }} *
                                </Label>
                                <Input
                                    id="display_name"
                                    name="display_name"
                                    required
                                    :value="customer.display_name"
                                />
                                <InputError :message="errors.display_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="customer_status">
                                    {{ t('customers.status') }}
                                </Label>
                                <Select
                                    name="customer_status"
                                    :default-value="customer.status"
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            {{ t('customers.status.active') }}
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            {{ t('customers.status.inactive') }}
                                        </SelectItem>
                                        <SelectItem value="blocked">
                                            {{ t('customers.status.blocked') }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="first_name">{{ t('customers.firstName') }}</Label>
                                <Input id="first_name" name="first_name" :value="customer.first_name ?? ''" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="last_name">{{ t('customers.lastName') }}</Label>
                                <Input id="last_name" name="last_name" :value="customer.last_name ?? ''" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="gender">{{ t('customers.gender') }}</Label>
                                <Select name="gender" :default-value="customer.gender ?? 'not_specified'">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="not_specified">
                                            {{ t('customers.gender.not_specified') }}
                                        </SelectItem>
                                        <SelectItem value="female">
                                            {{ t('customers.gender.female') }}
                                        </SelectItem>
                                        <SelectItem value="male">
                                            {{ t('customers.gender.male') }}
                                        </SelectItem>
                                        <SelectItem value="other">
                                            {{ t('customers.gender.other') }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="birth_date">{{ t('customers.birthDate') }}</Label>
                                <Input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    dir="ltr"
                                    :value="customer.birth_date ?? ''"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ t('customers.birthDateHint') }}
                                </p>
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="acquisition_note">
                                    {{ t('customers.acquisitionNote') }}
                                </Label>
                                <Input
                                    id="acquisition_note"
                                    name="acquisition_note"
                                    :value="customer.acquisition_note ?? ''"
                                />
                            </div>
                            <input type="hidden" name="acquisition_channel" :value="customer.acquisition_channel" />
                            <div class="sm:col-span-2">
                                <Button type="submit" :disabled="processing">
                                    <Spinner v-if="processing" />
                                    {{ t('common.save') }}
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>

                <!-- Tags -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('customers.tags') }}</CardTitle>
                        <CardDescription v-if="isPrivileged">
                            {{ t('customers.tag.manage') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <template v-if="customer.tags.length > 0">
                                <div
                                    v-for="tagItem in customer.tags"
                                    :key="tagItem.id"
                                    class="flex items-center gap-1"
                                >
                                    <Badge variant="outline">{{ tagItem.name }}</Badge>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        :title="t('common.actions')"
                                        @click="
                                            router.delete(
                                                detachTag({
                                                    customer: customer.id,
                                                    tag: tagItem.id,
                                                }).url,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                            <span v-else class="text-sm text-muted-foreground">
                                {{ t('customers.tag.none') }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Select v-model="attachTagId">
                                <SelectTrigger class="w-full sm:w-56">
                                    <SelectValue :placeholder="t('customers.tag.pick')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="item in tags.filter(
                                            (x) => !customer.tags.some((y) => y.id === x.id),
                                        )"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                variant="outline"
                                :disabled="attachTagId === ''"
                                @click="
                                    router.post(
                                        attachTag({ customer: customer.id }).url,
                                        { tag_id: attachTagId },
                                    )
                                "
                            >
                                {{ t('customers.tag.attach') }}
                            </Button>
                        </div>

                        <Form
                            v-if="isPrivileged"
                            v-bind="TagController.store.form()"
                            v-slot="{ processing }"
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Input
                                name="name"
                                :placeholder="t('customers.tag.newName')"
                                class="w-full sm:w-56"
                                required
                            />
                            <Button type="submit" variant="ghost" :disabled="processing">
                                {{ t('customers.tag.create') }}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ================= Contacts ================= -->
            <TabsContent value="contacts" class="space-y-4">
                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.phone') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'contact'">
                            {{ t('customers.contact.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="customer.contacts.length" class="divide-y">
                            <li
                                v-for="contact in customer.contacts"
                                :key="contact.id"
                                class="flex items-center justify-between gap-3 py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span dir="ltr" class="font-medium">
                                        {{ contact.value }}
                                    </span>
                                    <Badge v-if="contact.is_primary" variant="secondary" class="text-xs">
                                        {{ t('customers.contact.primary') }}
                                    </Badge>
                                    <Badge variant="outline" class="text-xs">
                                        {{ contactTypeLabel(contact.type) }}
                                    </Badge>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="
                                        router.delete(
                                            CustomerContactController.destroy.url({
                                                customer: customer.id,
                                                contact: contact.id,
                                            }) as unknown as string,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">—</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.createSocial') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'social'">
                            {{ t('customers.social.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="customer.social_accounts.length" class="divide-y">
                            <li
                                v-for="social in customer.social_accounts"
                                :key="social.id"
                                class="flex items-center justify-between gap-3 py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        {{ socialLabel(social.platform) }}
                                    </Badge>
                                    <span dir="ltr" class="font-medium">
                                        {{ social.username }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="
                                        router.delete(
                                            CustomerSocialAccountController.destroy.url({
                                                customer: customer.id,
                                                socialAccount: social.id,
                                            }) as unknown as string,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">—</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.address.add') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'address'">
                            {{ t('customers.address.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="!isPrivileged"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('customers.address.restricted') }}
                        </p>
                        <ul v-if="customer.addresses.length" class="divide-y">
                            <li
                                v-for="address in customer.addresses"
                                :key="address.id"
                                class="flex items-start justify-between gap-3 py-2"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ address.title }}
                                        <Badge
                                            v-if="address.is_default"
                                            variant="secondary"
                                            class="ms-1 text-xs"
                                        >
                                            {{ t('customers.address.default') }}
                                        </Badge>
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ [address.province, address.city].filter(Boolean).join('، ') || '—' }}
                                    </p>
                                    <p
                                        v-if="isPrivileged && address.address_line"
                                        class="text-sm"
                                    >
                                        {{ address.address_line }}
                                    </p>
                                    <p
                                        v-if="isPrivileged && address.postal_code"
                                        dir="ltr"
                                        class="text-right text-xs text-muted-foreground"
                                    >
                                        {{ address.postal_code }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="
                                        router.delete(
                                            CustomerAddressController.destroy.url({
                                                customer: customer.id,
                                                address: address.id,
                                            }) as unknown as string,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">—</p>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ================= Interests: preferences, measurements, consents ===== -->
            <TabsContent value="interests" class="space-y-4">
                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.preference.add') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'preference'">
                            {{ t('customers.preference.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="customer.preferences.length" class="divide-y">
                            <li
                                v-for="pref in customer.preferences"
                                :key="pref.id"
                                class="flex items-center justify-between gap-3 py-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ pref.value_label }}</span>
                                    <Badge variant="outline" class="text-xs">
                                        {{ t(`customers.preference.category.${pref.category}`) }}
                                    </Badge>
                                    <Badge :variant="levelVariant(pref.level)" class="text-xs">
                                        {{ t(`customers.preference.level.${pref.level ?? 'neutral'}`) }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ t(`customers.preference.source.${pref.source}`) }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="
                                        router.delete(
                                            CustomerPreferenceController.destroy.url({
                                                customer: customer.id,
                                                preference: pref.id,
                                            }) as unknown as string,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('customers.preference.empty') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.measurement.add') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'measurement'">
                            {{ t('customers.measurement.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="customer.measurements.length" class="divide-y">
                            <li
                                v-for="measurement in customer.measurements"
                                :key="measurement.id"
                                class="flex items-center justify-between gap-3 py-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span dir="ltr" class="font-mono text-sm">
                                        {{ measurement.code }}
                                    </span>
                                    <span class="font-medium">
                                        {{ measurement.value_text ?? measurement.value_number ?? '—' }}
                                        <span v-if="measurement.unit" class="text-xs text-muted-foreground">
                                            {{ measurement.unit }}
                                        </span>
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatJalaliDate(measurement.recorded_at) }} ·
                                        {{ t(`customers.measurement.source.${measurement.source}`) }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="
                                        router.delete(
                                            CustomerMeasurementController.destroy.url({
                                                customer: customer.id,
                                                measurement: measurement.id,
                                            }) as unknown as string,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('customers.measurement.empty') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.consent.add') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'consent'">
                            {{ t('customers.consent.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="customer.consents.length" class="divide-y">
                            <li
                                v-for="consent in customer.consents"
                                :key="consent.id"
                                class="flex items-center gap-2 py-2"
                            >
                                <Badge :variant="consentVariant(consent.status)" class="text-xs">
                                    {{ t(`customers.consent.status.${consent.status}`) }}
                                </Badge>
                                <span class="text-sm">
                                    {{ t(`customers.consent.channel.${consent.channel}`) }}
                                    ·
                                    {{ t(`customers.consent.purpose.${consent.purpose}`) }}
                                </span>
                                <span class="ms-auto text-xs text-muted-foreground">
                                    {{ formatJalaliDate(consent.captured_at) }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('customers.consent.empty') }}
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ================= Notes ================= -->
            <TabsContent value="notes" class="space-y-4">
                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">{{ t('customers.tab.notes') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Form
                            v-bind="CustomerNoteController.store.form({ customer: customer.id })"
                            v-slot="{ processing }"
                            class="space-y-3"
                        >
                            <div class="grid gap-2">
                                <Label for="note-body">{{ t('customers.note.body') }}</Label>
                                <Textarea id="note-body" name="body" required rows="3" />
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <Select
                                    v-if="isPrivileged"
                                    name="visibility"
                                    default-value="team"
                                >
                                    <SelectTrigger class="w-44">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="team">
                                            {{ t('customers.note.visibility.team') }}
                                        </SelectItem>
                                        <SelectItem value="private">
                                            {{ t('customers.note.visibility.private') }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="submit" :disabled="processing">
                                    <Spinner v-if="processing" />
                                    {{ t('customers.note.add') }}
                                </Button>
                            </div>
                        </Form>

                        <Separator />

                        <ul v-if="notes.length" class="space-y-3">
                            <li
                                v-for="note in notes"
                                :key="note.id"
                                class="rounded-md border p-3 text-sm"
                            >
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            v-if="note.visibility === 'private'"
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{ t('customers.note.private') }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">
                                            {{ note.author ?? '—' }} ·
                                            {{ formatJalaliDateTime(note.created_at) }}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="
                                            router.delete(
                                                CustomerNoteController.destroy.url({
                                                    customer: customer.id,
                                                    note: note.id,
                                                }) as unknown as string,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <p class="leading-6 whitespace-pre-line">{{ note.body }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('customers.note.empty') }}
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ================= Interactions ================= -->
            <TabsContent value="interactions" class="space-y-4">
                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle class="text-base">
                            {{ t('customers.tab.interactions') }}
                        </CardTitle>
                        <Button variant="outline" size="sm" @click="dialog = 'interaction'">
                            {{ t('customers.interaction.add') }}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="interactions.length" class="space-y-3">
                            <li
                                v-for="interaction in interactions"
                                :key="interaction.id"
                                class="rounded-md border p-3 text-sm"
                            >
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        {{ t(`customers.interaction.type.${interaction.type}`) }}
                                    </Badge>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ t(`customers.interaction.channel.${interaction.channel}`) }}
                                    </Badge>
                                    <Badge
                                        v-if="interaction.outcome"
                                        variant="default"
                                        class="text-xs"
                                    >
                                        {{ t(`customers.interaction.outcome.${interaction.outcome}`) }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatJalaliDateTime(interaction.occurred_at) }} ·
                                        {{ interaction.author ?? '—' }}
                                    </span>
                                </div>
                                <p v-if="interaction.subject" class="font-medium">
                                    {{ interaction.subject }}
                                </p>
                                <p class="leading-6 whitespace-pre-line">
                                    {{ interaction.summary }}
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('customers.interaction.empty') }}
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>

    <!-- Shared add-record dialog -->
    <Dialog
        :open="dialog !== null"
        @update:open="(value: boolean) => (dialog = value ? dialog : null)"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader v-if="dialog !== null">
                <DialogTitle>{{ dialogMeta[dialog].title }}</DialogTitle>
                <DialogDescription v-if="dialogMeta[dialog].description">
                    {{ dialogMeta[dialog].description }}
                </DialogDescription>
            </DialogHeader>

            <!-- Add contact -->
            <Form
                v-if="dialog === 'contact'"
                v-bind="CustomerContactController.store.form({ customer: customer.id })"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="contact_type">{{ t('customers.contact.type') }}</Label>
                        <Select name="contact_type" default-value="mobile">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="mobile">{{ t('customers.contact.type.mobile') }}</SelectItem>
                                <SelectItem value="landline">{{ t('customers.contact.type.landline') }}</SelectItem>
                                <SelectItem value="email">{{ t('customers.contact.type.email') }}</SelectItem>
                                <SelectItem value="other">{{ t('customers.contact.type.other') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact_value">{{ t('customers.contact.value') }}</Label>
                        <Input id="contact_value" name="value" dir="ltr" required />
                        <InputError :message="errors.value" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="contact_label">{{ t('customers.contact.label') }}</Label>
                    <Select name="label" default-value="personal">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="personal">{{ t('customers.contact.label.personal') }}</SelectItem>
                            <SelectItem value="spouse">{{ t('customers.contact.label.spouse') }}</SelectItem>
                            <SelectItem value="work">{{ t('customers.contact.label.work') }}</SelectItem>
                            <SelectItem value="family">{{ t('customers.contact.label.family') }}</SelectItem>
                            <SelectItem value="other">{{ t('customers.contact.type.other') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox name="is_primary" />
                    {{ t('customers.contact.isPrimary') }}
                </label>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add social -->
            <Form
                v-if="dialog === 'social'"
                v-bind="CustomerSocialAccountController.store.form({ customer: customer.id })"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid gap-2">
                    <Label for="social_platform">{{ t('customers.social.platform') }}</Label>
                    <Select name="platform" default-value="instagram">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="instagram">{{ t('customers.social.platform.instagram') }}</SelectItem>
                            <SelectItem value="telegram">{{ t('customers.social.platform.telegram') }}</SelectItem>
                            <SelectItem value="whatsapp">{{ t('customers.social.platform.whatsapp') }}</SelectItem>
                            <SelectItem value="other">{{ t('customers.social.platform.other') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="social_username">{{ t('customers.social.username') }}</Label>
                    <Input id="social_username" name="username" dir="ltr" required />
                    <InputError :message="errors.username" />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add address -->
            <Form
                v-if="dialog === 'address'"
                v-bind="CustomerAddressController.store.form({ customer: customer.id })"
                v-slot="{ processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid gap-2">
                    <Label for="address_title">{{ t('customers.address.title') }}</Label>
                    <Input id="address_title" name="title" required />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="address_province">{{ t('customers.address.province') }}</Label>
                        <Input id="address_province" name="province" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address_city">{{ t('customers.address.city') }}</Label>
                        <Input id="address_city" name="city" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="address_line">{{ t('customers.address.line') }}</Label>
                    <Textarea id="address_line" name="address_line" rows="2" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="address_postal">{{ t('customers.address.postalCode') }}</Label>
                        <Input id="address_postal" name="postal_code" dir="ltr" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address_recipient">{{ t('customers.address.recipient') }}</Label>
                        <Input id="address_recipient" name="recipient_name" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox name="is_default" />
                    {{ t('customers.address.isDefault') }}
                </label>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add interaction -->
            <Form
                v-if="dialog === 'interaction'"
                v-bind="CustomerInteractionController.store.form({ customer: customer.id })"
                v-slot="{ processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="interaction_type">{{ t('customers.interaction.type') }}</Label>
                        <Select name="interaction_type" default-value="message">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="message">{{ t('customers.interaction.type.message') }}</SelectItem>
                                <SelectItem value="call">{{ t('customers.interaction.type.call') }}</SelectItem>
                                <SelectItem value="follow_up">{{ t('customers.interaction.type.follow_up') }}</SelectItem>
                                <SelectItem value="complaint">{{ t('customers.interaction.type.complaint') }}</SelectItem>
                                <SelectItem value="question">{{ t('customers.interaction.type.question') }}</SelectItem>
                                <SelectItem value="purchase_related">{{ t('customers.interaction.type.purchase_related') }}</SelectItem>
                                <SelectItem value="other">{{ t('customers.interaction.type.other') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="interaction_channel">{{ t('customers.interaction.channel') }}</Label>
                        <Select name="channel" default-value="instagram">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="instagram">{{ t('customers.interaction.channel.instagram') }}</SelectItem>
                                <SelectItem value="whatsapp">{{ t('customers.interaction.channel.whatsapp') }}</SelectItem>
                                <SelectItem value="telegram">{{ t('customers.interaction.channel.telegram') }}</SelectItem>
                                <SelectItem value="phone">{{ t('customers.interaction.channel.phone') }}</SelectItem>
                                <SelectItem value="in_person">{{ t('customers.interaction.channel.in_person') }}</SelectItem>
                                <SelectItem value="other">{{ t('customers.interaction.channel.other') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="interaction_subject">{{ t('customers.interaction.subject') }}</Label>
                    <Input id="interaction_subject" name="subject" />
                </div>
                <div class="grid gap-2">
                    <Label for="interaction_summary">{{ t('customers.interaction.summary') }}</Label>
                    <Textarea id="interaction_summary" name="summary" rows="3" required />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="interaction_outcome">{{ t('customers.interaction.outcome') }}</Label>
                        <Select name="outcome">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="purchase">{{ t('customers.interaction.outcome.purchase') }}</SelectItem>
                                <SelectItem value="no_purchase">{{ t('customers.interaction.outcome.no_purchase') }}</SelectItem>
                                <SelectItem value="waiting">{{ t('customers.interaction.outcome.waiting') }}</SelectItem>
                                <SelectItem value="resolved">{{ t('customers.interaction.outcome.resolved') }}</SelectItem>
                                <SelectItem value="unresolved">{{ t('customers.interaction.outcome.unresolved') }}</SelectItem>
                                <SelectItem value="other">{{ t('customers.interaction.outcome.other') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="interaction_follow_up">
                            {{ t('customers.interaction.followUpAt') }}
                        </Label>
                        <Input id="interaction_follow_up" name="follow_up_at" type="date" dir="ltr" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add consent -->
            <Form
                v-if="dialog === 'consent'"
                v-bind="CustomerConsentController.store.form({ customer: customer.id })"
                v-slot="{ processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="consent_channel">{{ t('customers.consent.channel') }}</Label>
                        <Select name="channel" default-value="sms">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sms">{{ t('customers.consent.channel.sms') }}</SelectItem>
                                <SelectItem value="whatsapp">{{ t('customers.consent.channel.whatsapp') }}</SelectItem>
                                <SelectItem value="instagram">{{ t('customers.consent.channel.instagram') }}</SelectItem>
                                <SelectItem value="telegram">{{ t('customers.consent.channel.telegram') }}</SelectItem>
                                <SelectItem value="email">{{ t('customers.consent.channel.email') }}</SelectItem>
                                <SelectItem value="phone">{{ t('customers.consent.channel.phone') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="consent_purpose">{{ t('customers.consent.purpose') }}</Label>
                        <Select name="purpose" default-value="marketing">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="transactional">{{ t('customers.consent.purpose.transactional') }}</SelectItem>
                                <SelectItem value="marketing">{{ t('customers.consent.purpose.marketing') }}</SelectItem>
                                <SelectItem value="service">{{ t('customers.consent.purpose.service') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="consent_status">{{ t('customers.consent.status') }}</Label>
                    <Select name="status" default-value="unknown">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="unknown">{{ t('customers.consent.status.unknown') }}</SelectItem>
                            <SelectItem value="granted">{{ t('customers.consent.status.granted') }}</SelectItem>
                            <SelectItem value="denied">{{ t('customers.consent.status.denied') }}</SelectItem>
                            <SelectItem value="withdrawn">{{ t('customers.consent.status.withdrawn') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="consent_note">{{ t('customers.acquisitionNote') }}</Label>
                    <Input id="consent_note" name="evidence_note" />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add preference -->
            <Form
                v-if="dialog === 'preference'"
                v-bind="CustomerPreferenceController.store.form({ customer: customer.id })"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid gap-2">
                    <Label for="preference_category">{{ t('customers.preference.category') }}</Label>
                    <Select name="preference_category" default-value="color">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="product_category">{{ t('customers.preference.category.product_category') }}</SelectItem>
                            <SelectItem value="color">{{ t('customers.preference.category.color') }}</SelectItem>
                            <SelectItem value="fabric">{{ t('customers.preference.category.fabric') }}</SelectItem>
                            <SelectItem value="style">{{ t('customers.preference.category.style') }}</SelectItem>
                            <SelectItem value="occasion">{{ t('customers.preference.category.occasion') }}</SelectItem>
                            <SelectItem value="price_sensitivity">{{ t('customers.preference.category.price_sensitivity') }}</SelectItem>
                            <SelectItem value="budget_level">{{ t('customers.preference.category.budget_level') }}</SelectItem>
                            <SelectItem value="brand">{{ t('customers.preference.category.brand') }}</SelectItem>
                            <SelectItem value="other">{{ t('customers.preference.category.other') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="preference_value">{{ t('customers.preference.value') }}</Label>
                        <Input id="preference_value" name="value_label" required placeholder="مشکی" />
                        <InputError :message="errors.value_label" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="preference_level">{{ t('customers.preference.level') }}</Label>
                        <Select name="preference_level" default-value="like">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="like">{{ t('customers.preference.level.like') }}</SelectItem>
                                <SelectItem value="dislike">{{ t('customers.preference.level.dislike') }}</SelectItem>
                                <SelectItem value="neutral">{{ t('customers.preference.level.neutral') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <input type="hidden" name="value_code" value="manual" />
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- Add measurement -->
            <Form
                v-if="dialog === 'measurement'"
                v-bind="CustomerMeasurementController.store.form({ customer: customer.id })"
                v-slot="{ processing }"
                class="grid gap-4"
                @success="dialog = null"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="measurement_code">{{ t('customers.measurement.code') }}</Label>
                        <Input
                            id="measurement_code"
                            name="measurement_code"
                            dir="ltr"
                            required
                            :placeholder="t('customers.measurement.codePlaceholder')"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="measurement_unit">{{ t('customers.measurement.unit') }}</Label>
                        <Input id="measurement_unit" name="unit" dir="ltr" placeholder="cm / size / qovare" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="measurement_value_text">{{ t('customers.measurement.valueText') }}</Label>
                        <Input id="measurement_value_text" name="value_text" dir="ltr" placeholder="M / 40" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="measurement_value_number">{{ t('customers.measurement.valueNumber') }}</Label>
                        <Input
                            id="measurement_value_number"
                            name="value_number"
                            dir="ltr"
                            type="number"
                            step="0.5"
                            min="0"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
