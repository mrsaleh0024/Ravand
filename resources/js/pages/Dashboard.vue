<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    Clock,
    Coins,
    Inbox,
    LogIn,
    LogOut,
    ShieldAlert,
    Users,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import EventsFeed from '@/components/dashboard/EventsFeed.vue';
import LoginChart from '@/components/dashboard/LoginChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import SystemHealthCard from '@/components/dashboard/SystemHealthCard.vue';
import { t, tf } from '@/lib/i18n';
import { formatJalaliDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as membersIndex } from '@/routes/panel/members';
import { index as requestsIndex } from '@/routes/panel/reset-requests';
import { live } from '@/routes/panel';
import type { LivePayload, TenantRole } from '@/types';

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

const role = computed(() => page.props.auth.role as string | null);
const canViewLive = computed(() => role.value === 'owner' || role.value === 'manager');

const data = ref<LivePayload | null>(null);
const updatedAt = ref<string | null>(null);
const loading = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const fetchLive = async () => {
    if (! canViewLive.value || document.hidden) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(live.url(), {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            data.value = (await response.json()) as LivePayload;
            updatedAt.value = new Date().toISOString();
        }
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    void fetchLive();

    // Live dashboard: light JSON polling every 10 seconds (ADR-023). The
    // interval pauses while the tab is hidden to save resources.
    timer = setInterval(() => void fetchLive(), 10000);
});

onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});

const stats = computed(() => data.value?.stats);
const series = computed(() => data.value?.chart.series ?? []);
const events = computed(() => data.value?.events ?? []);
const system = computed(() => data.value?.system);

const byRole = computed(() => {
    const map = stats.value?.by_role;

    if (! map) {
        return [];
    }

    return (['owner', 'manager', 'operator'] as TenantRole[]).map((key) => ({
        key,
        label: t(`role.${key}`),
        total: map[key] ?? 0,
    }));
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading :title="t('dashboard.title')" />
            <div class="flex items-center gap-2">
                <Badge v-if="role" variant="outline">
                    {{ t(`role.${role}`) }}
                </Badge>
                <Badge
                    v-if="canViewLive"
                    variant="outline"
                    class="gap-1.5 text-emerald-700 dark:text-emerald-400"
                >
                    <span
                        class="relative flex size-2"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-emerald-600"
                        />
                    </span>
                    {{ t('common.live') }}
                </Badge>
            </div>
        </div>

        <!-- Store overview (always visible, no polling needed) -->
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

        <template v-if="canViewLive">
            <!-- Live stats -->
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    :label="t('dashboard.loginsToday')"
                    :value="stats?.logins_today ?? '—'"
                    :icon="LogIn"
                />
                <StatCard
                    :label="t('dashboard.failedToday')"
                    :value="stats?.failed_today ?? '—'"
                    :icon="ShieldAlert"
                    :tone="(stats?.failed_today ?? 0) > 0 ? 'danger' : 'default'"
                />
                <StatCard
                    :label="t('dashboard.activeMembers')"
                    :value="stats?.active_members ?? '—'"
                    :icon="Users"
                />
                <StatCard
                    :label="t('dashboard.pendingRequests')"
                    :value="stats?.pending_reset_requests ?? '—'"
                    :icon="Inbox"
                    :tone="(stats?.pending_reset_requests ?? 0) > 0 ? 'warning' : 'default'"
                />
            </div>

            <!-- Chart + access control -->
            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-sm font-medium">
                            {{ t('dashboard.loginChart') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <LoginChart :series="series" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium">
                            {{ t('dashboard.accessControl') }}
                        </CardTitle>
                        <CardDescription class="text-xs">
                            {{ t('dashboard.byRole') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="row in byRole"
                            :key="row.key"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span class="text-muted-foreground">
                                {{ row.label }}
                            </span>
                            <span class="font-semibold tabular-nums">
                                {{ row.total }}
                            </span>
                        </div>
                    </CardContent>
                    <CardFooter class="gap-2">
                        <Link
                            v-if="role !== 'operator'"
                            :href="membersIndex()"
                            class="flex-1"
                            as-child
                        >
                            <Badge
                                variant="outline"
                                class="w-full cursor-pointer justify-center py-1.5 hover:bg-accent"
                            >
                                {{ t('dashboard.viewMembers') }}
                            </Badge>
                        </Link>
                        <Link
                            v-if="role !== 'operator'"
                            :href="requestsIndex()"
                            class="flex-1"
                            as-child
                        >
                            <Badge
                                variant="outline"
                                class="w-full cursor-pointer justify-center py-1.5 hover:bg-accent"
                            >
                                {{ t('dashboard.viewRequests') }}
                            </Badge>
                        </Link>
                    </CardFooter>
                </Card>
            </div>

            <!-- Live events feed -->
            <Card>
                <CardHeader class="flex-row items-center justify-between space-y-0">
                    <div class="space-y-1">
                        <CardTitle class="text-sm font-medium">
                            {{ t('dashboard.eventsFeed') }}
                        </CardTitle>
                        <CardDescription class="text-xs">
                            {{ t('dashboard.liveFeedHint') }}
                        </CardDescription>
                    </div>
                    <Activity
                        class="size-4"
                        :class="loading ? 'animate-pulse text-emerald-600' : 'text-muted-foreground'"
                    />
                </CardHeader>
                <CardContent>
                    <EventsFeed :events="events" />
                </CardContent>
            </Card>

            <!-- System health (owner only) -->
            <Card v-if="system">
                <CardHeader class="flex-row items-center justify-between space-y-0">
                    <div class="space-y-1">
                        <CardTitle class="text-sm font-medium">
                            {{ t('dashboard.systemHealth') }}
                        </CardTitle>
                        <CardDescription
                            v-if="updatedAt"
                            class="text-xs"
                        >
                            {{ t('common.lastUpdate') }}:
                            {{ formatJalaliDateTime(updatedAt) }}
                        </CardDescription>
                    </div>
                    <LogOut class="hidden size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <SystemHealthCard :system="system" />
                </CardContent>
            </Card>
        </template>

        <Card v-else class="border-dashed">
            <CardHeader>
                <CardTitle class="text-sm font-medium">
                    {{ t('dashboard.welcome') }}،
                    {{ page.props.auth.user?.name }}
                </CardTitle>
                <CardDescription class="leading-7">
                    {{ t('settings.description') }}
                </CardDescription>
            </CardHeader>
        </Card>
    </div>
</template>
