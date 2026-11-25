<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ScrollText,
    UserRound,
    ShieldCheck,
    Palette,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { t } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as auditIndex } from '@/routes/tenant/audit';
import { edit as editTenant } from '@/routes/tenant/settings';
import type { NavItem } from '@/types';

const page = usePage();
const role = computed(() => page.props.auth.role);

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('nav.profile'),
            href: editProfile(),
            icon: UserRound,
        },
        {
            title: t('nav.security'),
            href: editSecurity(),
            icon: ShieldCheck,
        },
        {
            title: t('nav.appearance'),
            href: editAppearance(),
            icon: Palette,
        },
    ];

    // Store-scoped areas are visible for roles that may actually open them;
    // server-side gates still enforce the real decision (spec 8.2).
    if (role.value === 'owner' || role.value === 'manager') {
        items.unshift(
            {
                title: t('nav.storeSettings'),
                href: editTenant(),
                icon: Building2,
            },
            {
                title: t('nav.auditLog'),
                href: auditIndex(),
                icon: ScrollText,
            },
        );
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('common.settings')"
            :description="t('settings.description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
