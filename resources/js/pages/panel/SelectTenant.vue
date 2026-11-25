<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, store } from '@/routes/tenant';
import type { Membership } from '@/types';

defineProps<{
    memberships: Membership[];
}>();

const roleLabel = (role: Membership['role']) => t(`role.${role}`);
</script>

<template>
    <Head :title="t('tenant.selectTitle')" />

    <div
        dir="rtl"
        class="mx-auto flex min-h-svh w-full max-w-md flex-col justify-center gap-6 px-4 py-8"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex size-10 items-center justify-center rounded-lg border bg-neutral-50"
            >
                <Building2 class="size-5" />
            </div>
            <Heading
                :title="t('tenant.selectTitle')"
                :description="t('tenant.selectDescription')"
            />
        </div>

        <div v-if="memberships.length === 0" class="rounded-lg border p-5">
            <p class="text-sm font-medium">{{ t('tenant.noAccess') }}</p>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ t('tenant.noAccessHint') }}
            </p>
        </div>

        <div v-else class="flex flex-col gap-3">
            <Form
                v-for="membership in memberships"
                :key="membership.tenant_id"
                v-bind="store.form()"
                v-slot="{ processing }"
                class="contents"
            >
                <input
                    type="hidden"
                    name="tenant_id"
                    :value="membership.tenant_id"
                />

                <button
                    type="submit"
                    class="flex w-full items-center justify-between gap-3 rounded-lg border bg-white p-4 text-start transition-colors hover:bg-neutral-50 disabled:opacity-60"
                    :disabled="processing"
                >
                    <span class="flex flex-col gap-1">
                        <span class="text-sm font-medium">
                            {{ membership.tenant_name }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ t('tenant.currentRole') }}:
                            {{ roleLabel(membership.role) }}
                        </span>
                    </span>
                    <Badge variant="outline">
                        <Spinner v-if="processing" class="size-3" />
                        <template v-else>{{ t('tenant.enter') }}</template>
                    </Badge>
                </button>
            </Form>
        </div>
    </div>
</template>
