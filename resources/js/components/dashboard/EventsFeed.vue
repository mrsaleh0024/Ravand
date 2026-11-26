<script setup lang="ts">
import { formatJalaliDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LiveEvent } from '@/types';

defineProps<{
    events: LiveEvent[];
}>();

const toneFor = (action: string): string => {
    if (action.endsWith('_failed') || action.endsWith('invalid')) {
        return 'text-destructive';
    }
    if (action.endsWith('.issued') || action.endsWith('reset_requested')) {
        return 'text-amber-600 dark:text-amber-400';
    }
    return 'text-foreground';
};
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead class="w-28">{{ t('audit.time') }}</TableHead>
                <TableHead>{{ t('audit.actor') }}</TableHead>
                <TableHead>{{ t('audit.action') }}</TableHead>
                <TableHead class="hidden sm:table-cell">
                    {{ t('audit.ip') }}
                </TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableEmpty v-if="events.length === 0" :colspan="4">
                {{ t('dashboard.noEvents') }}
            </TableEmpty>
            <TableRow v-for="event in events" :key="event.id">
                <TableCell class="whitespace-nowrap text-xs">
                    {{ formatJalaliDateTime(event.occurred_at) }}
                </TableCell>
                <TableCell class="text-sm">
                    {{ event.actor ?? '—' }}
                </TableCell>
                <TableCell
                    class="text-sm"
                    :class="toneFor(event.action)"
                >
                    {{ t(`audit.action.${event.action}`) }}
                </TableCell>
                <TableCell
                    dir="ltr"
                    class="hidden text-left text-xs text-muted-foreground sm:table-cell"
                >
                    {{ event.ip_address ?? '—' }}
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
