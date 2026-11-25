<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, Building2, Check } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { t } from '@/lib/i18n';
import { store } from '@/routes/tenant';

const page = usePage();
const user = computed(() => page.props.auth.user);
const role = computed(() => page.props.auth.role);
const memberships = computed(() => page.props.auth.memberships ?? []);
const currentTenantId = computed(() => page.props.tenant?.id);
const { isMobile, state } = useSidebar();

const roleLabel = (value?: string | null) => (value ? t(`role.${value}`) : '');

const switchTo = (tenantId: string) => {
    if (tenantId === currentTenantId.value) {
        return;
    }

    router.post(
        store.url(),
        { tenant_id: tenantId },
        {
            onSuccess: () => router.flushAll(),
        },
    );
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ms-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <!-- Tenant switcher: active memberships of this user only. -->
                    <DropdownMenuLabel v-if="memberships.length > 0">
                        فروشگاه‌ها
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="membership in memberships"
                        :key="membership.tenant_id"
                        class="cursor-pointer"
                        @click="switchTo(membership.tenant_id)"
                    >
                        <Building2 class="me-2 size-4 shrink-0" />
                        <span class="flex-1 truncate">
                            {{ membership.tenant_name }}
                        </span>
                        <Check
                            v-if="membership.tenant_id === currentTenantId"
                            class="me-1 size-4"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="memberships.length > 0" />

                    <DropdownMenuLabel class="p-0 font-normal">
                        <div
                            class="flex items-center gap-2 px-1 py-1.5 text-start text-sm"
                        >
                            <UserInfo :user="user" :show-email="true" />
                            <span
                                v-if="role"
                                class="ms-auto rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground"
                            >
                                {{ roleLabel(role) }}
                            </span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
