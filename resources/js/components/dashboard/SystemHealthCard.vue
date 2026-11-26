<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { t } from '@/lib/i18n';
import type { SystemHealth } from '@/types';

const props = defineProps<{
    system: SystemHealth;
}>();

const rows = () => {
    const s = props.system;

    return [
        { label: t('dashboard.system.php'), value: s.php_version, ok: null },
        {
            label: t('dashboard.system.laravel'),
            value: s.laravel_version,
            ok: null,
        },
        {
            label: t('dashboard.system.environment'),
            value: s.environment,
            ok: s.environment === 'production' || s.environment === 'local',
        },
        { label: t('dashboard.system.debug'), value: s.debug ? t('common.yes') : t('common.no'), ok: !s.debug },
        { label: t('dashboard.system.db'), value: s.db_driver, ok: null },
        { label: t('dashboard.system.cache'), value: s.cache_driver, ok: s.cache_ok },
        { label: t('dashboard.system.queue'), value: s.queue_driver, ok: null },
        { label: t('dashboard.system.session'), value: s.session_driver, ok: null },
        {
            label: t('dashboard.system.baleCodeBot'),
            value: s.bale_code_bot ? t('bale.status.configured') : t('bale.status.notConfigured'),
            ok: s.bale_code_bot,
        },
        {
            label: t('dashboard.system.baleAdminBot'),
            value: s.bale_admin_bot ? t('bale.status.configured') : t('bale.status.notConfigured'),
            ok: s.bale_admin_bot,
        },
        {
            label: t('dashboard.system.owner2fa'),
            value: s.owner_2fa ? t('common.yes') : t('common.no'),
            ok: s.owner_2fa,
        },
        {
            label: t('dashboard.system.failedJobs'),
            value: s.failed_jobs,
            ok: s.failed_jobs === 0,
        },
    ];
};
</script>

<template>
    <ul class="divide-y text-sm">
        <li
            v-for="row in rows()"
            :key="row.label"
            class="flex items-center justify-between gap-3 py-2"
        >
            <span class="text-muted-foreground">{{ row.label }}</span>
            <span class="flex items-center gap-1.5 font-medium">
                <span dir="ltr">{{ row.value }}</span>
                <CircleCheck
                    v-if="row.ok === true"
                    class="size-4 text-emerald-600 dark:text-emerald-400"
                />
                <CircleAlert
                    v-else-if="row.ok === false"
                    class="size-4 text-red-600 dark:text-red-400"
                />
            </span>
        </li>
    </ul>

    <div v-if="system.log_tail.length > 0" class="mt-4">
        <p class="mb-2 text-xs font-medium text-muted-foreground">
            {{ t('dashboard.system.logs') }}
        </p>
        <pre
            dir="ltr"
            class="max-h-40 overflow-auto rounded-md border bg-muted/40 p-3 text-left text-[11px] leading-5 text-muted-foreground"
        >{{ system.log_tail.slice(-12).join('\n') }}</pre>
    </div>
    <p v-else class="mt-4 text-xs text-muted-foreground">
        {{ t('dashboard.system.logsEmpty') }}
    </p>
</template>
