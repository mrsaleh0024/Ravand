<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, Building2, LayoutGrid, Package, ScrollText, ShieldCheck, Users, UserSquare2 } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as inventoryIndex } from '@/routes/inventory';
import { index as ordersIndex } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { index as customersIndex } from '@/routes/customers';
import { index as membersIndex } from '@/routes/panel/members';
import { index as requestsIndex } from '@/routes/panel/reset-requests';
import { index as auditIndex } from '@/routes/tenant/audit';
import { edit as tenantSettingsEdit } from '@/routes/tenant/settings';
import type { NavItem } from '@/types';

const page = usePage();
const role = computed(() => page.props.auth.role);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.customers'),
            href: customersIndex(),
            icon: UserSquare2,
        },
        {
            title: t('nav.orders'),
            href: ordersIndex(),
            icon: Package,
        },
        {
            title: t('nav.catalog'),
            href: productsIndex(),
            icon: Boxes,
        },
        {
            title: t('nav.inventory'),
            href: inventoryIndex(),
            icon: ScrollText,
        },
    ];

    // Only real, working destinations are shown — never dead links (spec 6.4).
    if (role.value === 'owner' || role.value === 'manager') {
        items.push(
            {
                title: t('nav.members'),
                href: membersIndex(),
                icon: Users,
            },
            {
                title: t('nav.resetRequests'),
                href: requestsIndex(),
                icon: ShieldCheck,
            },
            {
                title: t('nav.storeSettings'),
                href: tenantSettingsEdit(),
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
</script>

<template>
    <!-- RTL-first shell: the navigation rail lives on the right (spec 6.2). -->
    <Sidebar collapsible="icon" variant="inset" side="right">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
