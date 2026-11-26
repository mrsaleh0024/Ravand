<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/password/request';

defineOptions({
    layout: {
        title: 'فراموشی رمز عبور',
        description:
            'شماره موبایل خود را وارد کنید تا درخواست بازنشانی رمز برای مدیر ثبت شود',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.forgotTitle')" />

    <div
        v-if="status"
        class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800 dark:border-emerald-800/30 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form
            v-bind="store.form()"
            :reset-on-success="['phone']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="phone">{{ t('auth.phone') }}</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    autocomplete="off"
                    dir="ltr"
                    inputmode="tel"
                    v-focus
                    :placeholder="t('auth.phonePlaceholder')"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="password-reset-request-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.submitResetRequest') }}
                </Button>
            </div>
        </Form>

        <div
            class="flex gap-2.5 rounded-md border bg-muted/40 p-3.5 text-sm leading-6 text-muted-foreground"
        >
            <Info class="mt-1 size-4 shrink-0" />
            <p>{{ t('auth.forgotNotice') }}</p>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{ t('auth.login') }}</TextLink>
        </div>
    </div>
</template>
