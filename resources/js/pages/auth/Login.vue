<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'ورود به حساب کاربری',
        description: 'شماره موبایل و رمز عبور خود را وارد کنید',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.loginTitle')" />

    <div
        v-if="status"
        class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800 dark:border-emerald-800/30 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="phone">{{ t('auth.phone') }}</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    required
                    dir="ltr"
                    inputmode="tel"
                    v-focus
                    :tabindex="1"
                    autocomplete="username"
                    :placeholder="t('auth.phonePlaceholder')"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <TextLink
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{ t('auth.forgotPassword') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="t('auth.password')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center gap-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ t('auth.rememberMe') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.login') }}
            </Button>
        </div>
    </Form>
</template>
