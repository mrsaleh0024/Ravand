<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Bot, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import BaleSettingsController from '@/actions/App/Http/Controllers/Settings/BaleSettingsController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { edit } from '@/routes/security';
import type { BaleConfig, BaleTestResult } from '@/types/auth';

type Props = {
    passwordRules: string;
    bale: BaleConfig;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'امنیت',
                href: edit(),
            },
        ],
    },
});

const page = usePage();

const baleTest = computed(
    // Inertia v3 exposes session flash at the page level (not inside props).
    () => page.flash?.baleTest as BaleTestResult | undefined,
);
</script>

<template>
    <Head :title="t('nav.security')" />

    <h1 class="sr-only">{{ t('nav.security') }}</h1>

    <div class="space-y-12">
        <section class="space-y-6">
            <Heading
                variant="small"
                :title="t('auth.resetPasswordTitle')"
                :description="t('settings.description')"
            />

            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password">
                        {{ t('auth.password') }}
                    </Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        :placeholder="t('auth.password')"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">
                        {{ t('auth.newPassword') }}
                    </Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        :placeholder="t('auth.newPassword')"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        {{ t('auth.confirmPassword') }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        :placeholder="t('auth.confirmPassword')"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        {{ t('common.save') }}
                    </Button>
                </div>
            </Form>
        </section>

        <!-- Bale 2FA — owner only -->
        <section v-if="bale.isOwner" class="space-y-6">
            <Heading
                variant="small"
                :title="t('bale.title')"
                :description="t('bale.description')"
            />

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <Bot class="size-4" />
                        {{ t('bale.status.codeBot') }} /
                        {{ t('bale.status.adminBot') }}
                    </CardTitle>
                    <CardDescription class="flex flex-wrap gap-3 text-xs">
                        <span class="flex items-center gap-1.5">
                            {{ t('bale.status.codeBot') }}:
                            <Badge
                                v-if="bale.codeBot"
                                class="bg-emerald-600 text-white hover:bg-emerald-600"
                            >
                                {{ t('bale.status.configured') }}
                            </Badge>
                            <Badge v-else variant="outline">
                                {{ t('bale.status.notConfigured') }}
                            </Badge>
                        </span>
                        <span class="flex items-center gap-1.5">
                            {{ t('bale.status.adminBot') }}:
                            <Badge
                                v-if="bale.adminBot"
                                class="bg-emerald-600 text-white hover:bg-emerald-600"
                            >
                                {{ t('bale.status.configured') }}
                            </Badge>
                            <Badge v-else variant="outline">
                                {{ t('bale.status.notConfigured') }}
                            </Badge>
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <Form
                        v-bind="BaleSettingsController.update.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing }"
                        class="space-y-3"
                    >
                        <div class="grid gap-2">
                            <Label for="bale_chat_id">
                                {{ t('bale.chatId') }}
                            </Label>
                            <Input
                                id="bale_chat_id"
                                type="text"
                                name="bale_chat_id"
                                dir="ltr"
                                inputmode="numeric"
                                :default-value="bale.chatId ?? undefined"
                                autocomplete="off"
                                placeholder="123456789"
                            />
                            <InputError :message="errors.bale_chat_id" />
                            <p class="text-xs leading-5 text-muted-foreground">
                                {{ t('bale.chatIdHint') }}
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            data-test="save-bale-chat-button"
                        >
                            {{ t('bale.saveChatId') }}
                        </Button>
                    </Form>

                    <Form
                        v-bind="BaleSettingsController.test.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <input
                            type="hidden"
                            name="bot"
                            value="all"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            data-test="bale-test-button"
                        >
                            <ShieldCheck class="size-4" />
                            {{ t('bale.testSend') }}
                        </Button>

                        <span
                            v-if="baleTest"
                            class="flex flex-wrap gap-2 text-xs"
                        >
                            <Badge
                                v-for="(ok, bot) in baleTest"
                                :key="bot"
                                :variant="ok ? 'default' : 'destructive'"
                            >
                                {{ bot }}:
                                {{
                                    ok
                                        ? t('bale.status.configured')
                                        : t('bale.status.notConfigured')
                                }}
                            </Badge>
                        </span>
                    </Form>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
