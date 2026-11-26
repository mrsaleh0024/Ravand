<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header
        :class="
            variant === 'small'
                ? 'flex items-start justify-between gap-3'
                : 'mb-8 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'
        "
    >
        <div class="space-y-0.5">
            <h2
                :class="
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                "
            >
                {{ title }}
            </h2>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
