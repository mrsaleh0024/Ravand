<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatAmount, toAsciiDigits } from '@/lib/format';
import { t } from '@/lib/i18n';
import { update } from '@/routes/loyalty/settings';
import type { LoyaltySettings as LoyaltySettingsType } from '@/types';

const props = defineProps<{
    settings: LoyaltySettingsType;
    currency: string;
}>();

// Radix checkboxes submit "on", which Laravel's `boolean` rule rejects —
// so the toggle only drives a hidden 1/0 input (native form field).
const enabled = ref(props.settings.points_enabled);

// The server folds Persian digits before validation (ADR-025); the form
// simply mirrors what the user typed back as an ASCII integer string.
const amountStep = computed(() => toAsciiDigits(props.settings.amount_step));
const pointsPerStep = computed(() => String(props.settings.points_per_step));

const examplePoints = computed(() => {
    const step = Number(amountStep.value) || 0;
    const perStep = Number(pointsPerStep.value) || 0;

    if (step <= 0 || perStep <= 0) {
        return 0;
    }

    return Math.floor(4610000 / step) * perStep;
});
</script>

<template>
    <Head :title="t('loyalty.settings.title')" />

    <Card>
        <CardHeader>
            <CardTitle>{{ t('loyalty.settings.title') }}</CardTitle>
            <CardDescription>{{
                t('loyalty.settings.description')
            }}</CardDescription>
        </CardHeader>

        <CardContent>
            <Form
                v-bind="update.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                >
                    <input
                        type="hidden"
                        name="points_enabled"
                        :value="enabled ? '1' : '0'"
                    />
                    <Checkbox
                        v-model:model-value="enabled"
                        :tabindex="1"
                    />
                    <span class="space-y-1">
                        <span class="block text-sm font-medium">
                            {{ t('loyalty.settings.enable') }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            {{ t('loyalty.settings.enableHint') }}
                        </span>
                    </span>
                </label>
                <InputError :message="errors.points_enabled" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="amount_step">
                            {{ t('loyalty.settings.amountStep') }}
                        </Label>
                        <Input
                            id="amount_step"
                            name="amount_step"
                            type="text"
                            dir="ltr"
                            :default-value="amountStep"
                            required
                            :tabindex="2"
                            autocomplete="off"
                        />
                        <InputError :message="errors.amount_step" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="points_per_step">
                            {{ t('loyalty.settings.pointsPerStep') }}
                        </Label>
                        <Input
                            id="points_per_step"
                            name="points_per_step"
                            type="number"
                            dir="ltr"
                            min="1"
                            max="100000"
                            :default-value="pointsPerStep"
                            required
                            :tabindex="3"
                            autocomplete="off"
                        />
                        <InputError :message="errors.points_per_step" />
                    </div>
                </div>

                <div
                    class="rounded-md border bg-neutral-50 px-3 py-2 text-sm dark:bg-neutral-900"
                >
                    <span class="font-medium">
                        {{ t('loyalty.settings.rulePreview') }}:
                    </span>
                    {{ t('loyalty.type.earn') }} = {{ formatAmount(4610000, currency) }} →
                    <span class="font-semibold">
                        {{ examplePoints.toLocaleString('fa-IR') }}
                        {{ t('loyalty.pointsUnit') }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing" :tabindex="4">
                        <Spinner v-if="processing" />
                        {{ t('common.save') }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
