<script setup lang="ts">
import type { Component } from 'vue';

type Props = {
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    tone?: 'default' | 'warning' | 'danger' | 'success';
};

const props = withDefaults(defineProps<Props>(), {
    tone: 'default',
});

const toneClass = {
    default: 'text-foreground',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
    success: 'text-emerald-600 dark:text-emerald-400',
} as const;
</script>

<template>
    <div
        class="flex flex-col gap-1.5 rounded-lg border bg-card p-4 transition-colors hover:bg-accent/40"
    >
        <div class="flex items-center gap-2 text-muted-foreground">
            <component :is="props.icon" v-if="props.icon" class="size-4" />
            <span class="text-xs font-medium">{{ props.label }}</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span
                class="text-2xl font-semibold tabular-nums"
                :class="toneClass[props.tone]"
            >
                {{ props.value }}
            </span>
            <span v-if="props.hint" class="text-xs text-muted-foreground">
                {{ props.hint }}
            </span>
        </div>
    </div>
</template>
