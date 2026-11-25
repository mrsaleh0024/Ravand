<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Building2, Coins, Clock } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'داشبورد',
                href: dashboard(),
            },
        ],
    },
});

const roleLabel = () => t(`role.${page.props.auth.role}`);
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading :title="t('dashboard.title')" />
            <Badge v-if="page.props.auth.role" variant="outline">
                {{ roleLabel() }}
            </Badge>
        </div>

        <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardHeader class="pb-3">
                    <CardDescription class="flex items-center gap-2">
                        <Building2 class="size-4" />
                        {{ t('dashboard.store') }}
                    </CardDescription>
                    <CardTitle class="text-base font-semibold">
                        {{ page.props.tenant?.name ?? '—' }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardDescription class="flex items-center gap-2">
                        <Coins class="size-4" />
                        {{ t('dashboard.currency') }}
                    </CardDescription>
                    <CardTitle class="text-base font-semibold" dir="ltr">
                        {{ page.props.tenant?.default_currency ?? '—' }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardDescription class="flex items-center gap-2">
                        <Clock class="size-4" />
                        {{ t('dashboard.timezone') }}
                    </CardDescription>
                    <CardTitle class="text-base font-semibold" dir="ltr">
                        {{ page.props.tenant?.timezone ?? '—' }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card class="border-dashed">
            <CardHeader>
                <CardTitle class="text-sm font-medium">
                    {{ t('dashboard.welcome') }}،
                    {{ page.props.auth.user?.name }}
                </CardTitle>
                <CardDescription class="leading-7">
                    {{ t('dashboard.placeholder') }}
                </CardDescription>
            </CardHeader>
        </Card>
    </div>
</template>
