<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { t, type MessageKey } from '@/lib/i18n';
import { update } from '@/routes/tenant/settings';

type TenantSettings = {
    id: string;
    name: string;
    slug: string;
    default_currency: string;
    timezone: string;
    locale: string;
    calendar: string;
};

type Options = {
    currencies: string[];
    timezones: string[];
    locales: string[];
    calendars: string[];
};

defineProps<{
    tenant: TenantSettings;
    options: Options;
    canUpdate: boolean;
}>();

const dictKey = (prefix: string, code: string): MessageKey =>
    `${prefix}.${code}` as MessageKey;
</script>

<template>
    <Head :title="t('tenantSettings.title')" />

    <Card>
        <CardHeader>
            <CardTitle>{{ t('tenantSettings.title') }}</CardTitle>
            <CardDescription>{{
                t('tenantSettings.description')
            }}</CardDescription>
        </CardHeader>

        <CardContent>
            <p
                v-if="!canUpdate"
                class="mb-6 rounded-md border bg-neutral-50 px-3 py-2 text-sm"
            >
                {{ t('tenantSettings.readOnlyNotice') }}
            </p>

            <Form
                v-bind="update.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-2">
                    <Label for="name">{{
                        t('tenantSettings.storeName')
                    }}</Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="tenant.name"
                        required
                        :disabled="!canUpdate"
                        :tabindex="1"
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="slug">{{ t('tenantSettings.slug') }}</Label>
                    <Input
                        id="slug"
                        type="text"
                        dir="ltr"
                        :model-value="tenant.slug"
                        disabled
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('tenantSettings.slugHint') }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="default_currency">
                        {{ t('tenantSettings.currency') }}
                    </Label>
                    <Select
                        name="default_currency"
                        :default-value="tenant.default_currency"
                        :disabled="!canUpdate"
                    >
                        <SelectTrigger id="default_currency" :tabindex="2">
                            <SelectValue
                                :placeholder="
                                    t(
                                        dictKey(
                                            'tenantSettings.currency',
                                            tenant.default_currency,
                                        ),
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="code in options.currencies"
                                :key="code"
                                :value="code"
                            >
                                {{
                                    t(dictKey('tenantSettings.currency', code))
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.default_currency" />
                    <p class="text-xs text-muted-foreground">
                        {{ t('tenantSettings.currencyHint') }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="timezone">{{
                        t('tenantSettings.timezone')
                    }}</Label>
                    <Select
                        name="timezone"
                        :default-value="tenant.timezone"
                        :disabled="!canUpdate"
                    >
                        <SelectTrigger id="timezone" :tabindex="3">
                            <SelectValue :placeholder="tenant.timezone" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="code in options.timezones"
                                :key="code"
                                :value="code"
                            >
                                {{
                                    code === 'Asia/Tehran'
                                        ? 'تهران — Asia/Tehran'
                                        : code
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.timezone" />
                </div>

                <div class="grid gap-2">
                    <Label for="locale">{{ t('tenantSettings.locale') }}</Label>
                    <Select
                        name="locale"
                        :default-value="tenant.locale"
                        :disabled="!canUpdate"
                    >
                        <SelectTrigger id="locale" :tabindex="4">
                            <SelectValue
                                :placeholder="
                                    t(
                                        dictKey(
                                            'tenantSettings.locale',
                                            tenant.locale,
                                        ),
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="code in options.locales"
                                :key="code"
                                :value="code"
                            >
                                {{ t(dictKey('tenantSettings.locale', code)) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.locale" />
                </div>

                <div class="grid gap-2">
                    <Label for="calendar">{{
                        t('tenantSettings.calendar')
                    }}</Label>
                    <Select
                        name="calendar"
                        :default-value="tenant.calendar"
                        :disabled="!canUpdate"
                    >
                        <SelectTrigger id="calendar" :tabindex="5">
                            <SelectValue
                                :placeholder="
                                    t(
                                        dictKey(
                                            'tenantSettings.calendar',
                                            tenant.calendar,
                                        ),
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="code in options.calendars"
                                :key="code"
                                :value="code"
                            >
                                {{
                                    t(dictKey('tenantSettings.calendar', code))
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.calendar" />
                </div>

                <div v-if="canUpdate" class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing" :tabindex="6">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
