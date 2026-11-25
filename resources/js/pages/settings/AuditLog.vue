<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatJalaliDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { AuditEntry, PaginatedEntries } from '@/types';

type Props = {
    entries: PaginatedEntries<AuditEntry>;
};

defineProps<Props>();

const actionLabel = (action: string): string => t(`audit.action.${action}`);
</script>

<template>
    <Head :title="t('audit.title')" />

    <Card>
        <CardHeader>
            <CardTitle>{{ t('audit.title') }}</CardTitle>
            <CardDescription>{{ t('audit.description') }}</CardDescription>
        </CardHeader>

        <CardContent>
            <div class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-40">{{
                                t('audit.time')
                            }}</TableHead>
                            <TableHead>{{ t('audit.actor') }}</TableHead>
                            <TableHead>{{ t('audit.action') }}</TableHead>
                            <TableHead class="w-28">{{
                                t('audit.ip')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="entries.data.length === 0"
                            :colspan="4"
                        >
                            {{ t('audit.empty') }}
                        </TableEmpty>
                        <TableRow v-for="entry in entries.data" :key="entry.id">
                            <TableCell>
                                {{ formatJalaliDateTime(entry.occurred_at) }}
                            </TableCell>
                            <TableCell>{{
                                entry.actor?.name ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{ actionLabel(entry.action) }}
                                </Badge>
                            </TableCell>
                            <TableCell
                                dir="ltr"
                                class="text-xs text-muted-foreground"
                            >
                                {{ entry.ip_address ?? '—' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <nav
                v-if="entries.meta.last_page > 1"
                class="mt-4 flex flex-wrap items-center gap-2 text-sm"
                aria-label="pagination"
            >
                <template v-for="(link, i) in entries.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-1.5 transition-colors hover:bg-neutral-50"
                        :class="{ 'bg-neutral-900 text-white': link.active }"
                        preserve-scroll
                    >
                        {{ link.label }}
                    </Link>
                    <span v-else class="px-3 py-1.5 text-muted-foreground">
                        {{ link.label }}
                    </span>
                </template>
            </nav>
        </CardContent>
    </Card>
</template>
