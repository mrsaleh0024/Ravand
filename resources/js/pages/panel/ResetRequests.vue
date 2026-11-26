<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ResetRequestController from '@/actions/App/Http/Controllers/Panel/ResetRequestController';
import Heading from '@/components/Heading.vue';
import TempPasswordFlash from '@/components/TempPasswordFlash.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import { formatJalaliDateTime } from '@/lib/format';
import { index } from '@/routes/panel/reset-requests';
import type { ResetRequestItem } from '@/types';

defineProps<{
    requests: ResetRequestItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'درخواست‌های رمز',
                href: index(),
            },
        ],
    },
});

const statusVariant = (status: ResetRequestItem['status']) =>
    (
        {
            pending: 'outline',
            approved: 'default',
            rejected: 'secondary',
        } as const
    )[status];

const statusClass = (status: ResetRequestItem['status']) =>
    (
        {
            pending: 'text-amber-700 dark:text-amber-400',
            approved: 'bg-emerald-600 text-white hover:bg-emerald-600',
            rejected: 'text-muted-foreground',
        } as const
    )[status];
</script>

<template>
    <Head :title="t('resetRequests.title')" />

    <h1 class="sr-only">{{ t('resetRequests.title') }}</h1>

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="t('resetRequests.title')"
            :description="t('resetRequests.description')"
        />

        <Card>
            <CardContent class="px-0 pb-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('common.name') }}</TableHead>
                            <TableHead>{{ t('common.phone') }}</TableHead>
                            <TableHead>{{ t('common.status') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">
                                {{ t('resetRequests.requestedAt') }}
                            </TableHead>
                            <TableHead class="hidden md:table-cell">
                                {{ t('resetRequests.handledBy') }}
                            </TableHead>
                            <TableHead v-if="requests.some((r) => r.status === 'pending')" class="w-40" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="requests.length === 0" :colspan="6">
                            {{ t('resetRequests.empty') }}
                        </TableEmpty>
                        <TableRow
                            v-for="request in requests"
                            :key="request.id"
                        >
                            <TableCell class="font-medium">
                                {{ request.requested_by?.name ?? '—' }}
                            </TableCell>
                            <TableCell dir="ltr" class="text-left">
                                {{ request.phone }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="statusVariant(request.status)"
                                    :class="statusClass(request.status)"
                                >
                                    {{ t(`common.${request.status}`) }}
                                </Badge>
                            </TableCell>
                            <TableCell
                                class="hidden whitespace-nowrap text-xs text-muted-foreground sm:table-cell"
                            >
                                {{ formatJalaliDateTime(request.requested_at) }}
                            </TableCell>
                            <TableCell
                                class="hidden text-sm text-muted-foreground md:table-cell"
                            >
                                {{ request.handled_by ?? '—' }}
                            </TableCell>
                            <TableCell
                                v-if="request.status === 'pending'"
                                class="text-left"
                            >
                                <div class="flex justify-start gap-2">
                                    <Form
                                        v-bind="ResetRequestController.approve.form(request)"
                                        v-slot="{ processing: approving }"
                                        class="contents"
                                    >
                                        <Button
                                            type="submit"
                                            size="sm"
                                            :disabled="approving"
                                            data-test="approve-request-button"
                                        >
                                            {{ t('resetRequests.approve') }}
                                        </Button>
                                    </Form>
                                    <Form
                                        v-bind="ResetRequestController.reject.form(request)"
                                        v-slot="{ processing: rejecting }"
                                        class="contents"
                                    >
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="outline"
                                            :disabled="rejecting"
                                            data-test="reject-request-button"
                                        >
                                            {{ t('resetRequests.reject') }}
                                        </Button>
                                    </Form>
                                </div>
                            </TableCell>
                            <TableCell v-else-if="requests.some((r) => r.status === 'pending')" />
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <TempPasswordFlash />
</template>
