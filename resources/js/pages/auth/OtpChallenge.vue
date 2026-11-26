<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { t, tf } from '@/lib/i18n';
import { challenge, resend, verify } from '@/routes/otp';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'کد تایید دومرحله‌ای',
        description: 'کد ۶ رقمی ارسال‌شده در بله را وارد کنید',
    },
});

const props = defineProps<{
    phone?: string;
    cooldown?: number;
}>();

const page = usePage();

const remaining = ref(Math.max(0, props.cooldown ?? 0));
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        if (remaining.value > 0) {
            remaining.value -= 1;
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});

const resendDisabled = computed(() => remaining.value > 0);
const resendLabel = computed(() =>
    resendDisabled.value
        ? tf('auth.otpResendIn', { seconds: remaining.value })
        : t('auth.otpResend'),
);

const onResend = () => {
    if (resendDisabled.value) {
        return;
    }
    remaining.value = 60;
};
</script>

<template>
    <Head :title="t('auth.otpTitle')" />

    <div class="flex flex-col gap-6">
        <div class="grid gap-2 text-center">
            <p class="text-sm font-medium">
                {{ t('auth.otpSentTo') }}
            </p>
            <p
                v-if="phone"
                dir="ltr"
                class="text-sm text-muted-foreground"
            >
                {{ phone }}
            </p>
        </div>

        <Form
            v-bind="verify.form()"
            :reset-on-success="['code']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid justify-center gap-2">
                <Label for="code" class="sr-only">
                    {{ t('auth.otpTitle') }}
                </Label>
                <InputOTP
                    id="code"
                    name="code"
                    :maxlength="6"
                    dir="ltr"
                    :disabled="processing"
                    class="mx-auto"
                >
                    <InputOTPGroup>
                        <InputOTPSlot :index="0" />
                        <InputOTPSlot :index="1" />
                        <InputOTPSlot :index="2" />
                    </InputOTPGroup>
                    <InputOTPSeparator />
                    <InputOTPGroup>
                        <InputOTPSlot :index="3" />
                        <InputOTPSlot :index="4" />
                        <InputOTPSlot :index="5" />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="errors.code" class="text-center" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="otp-verify-button"
            >
                {{ t('auth.otpVerify') }}
            </Button>
        </Form>

        <Form
            v-bind="resend.form()"
            v-slot="{ processing }"
            class="contents"
            @success="onResend"
        >
            <Button
                type="submit"
                variant="outline"
                class="w-full"
                :disabled="processing || resendDisabled"
                data-test="otp-resend-button"
            >
                {{ resendLabel }}
            </Button>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{ t('auth.otpBackToLogin') }}</TextLink>
        </div>
    </div>
</template>
