<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const tenantName = page.props.tenant?.name;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ms-1 grid flex-1 text-start text-sm leading-tight">
        <span class="truncate font-semibold">
            {{ tenantName ?? 'رَوَند' }}
        </span>
        <span class="truncate text-xs text-muted-foreground">
            {{ tenantName ? 'پنل فروشگاه' : 'Ravand' }}
        </span>
    </div>
</template>
