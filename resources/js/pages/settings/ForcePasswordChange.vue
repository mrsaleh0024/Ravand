<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { update } from '@/routes/password/force';

defineOptions({
    layout: {
        title: 'تنظیم رمز عبور جدید',
        description: 'پس از ورود با رمز موقت، رمز شخصی خود را تنظیم کنید',
    },
});

const props = defineProps<{
    passwordRules?: string;
}>();
</script>

<template>
    <Head :title="t('auth.forcePasswordTitle')" />

    <div class="flex flex-col gap-6">
        <div
            class="mx-auto flex size-12 items-center justify-center rounded-full border bg-muted/40"
        >
            <KeyRound class="size-5 text-muted-foreground" />
        </div>

        <Form
            v-bind="update.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="password">{{ t('auth.newPassword') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
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
                    required
                    autocomplete="new-password"
                    :placeholder="t('auth.confirmPassword')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="force-password-button"
            >
                {{ t('auth.forcePasswordSubmit') }}
            </Button>
        </Form>
    </div>
</template>
