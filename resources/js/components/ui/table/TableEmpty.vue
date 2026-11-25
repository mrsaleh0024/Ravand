<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        colspan?: number;
    }>(),
    {
        colspan: 1,
    },
);
</script>

<template>
    <td
        data-slot="table-empty"
        :colspan="colspan"
        :class="cn('text-muted-foreground p-4 text-center text-sm', props.class)"
    >
        <div class="flex items-center justify-center py-6">
            <slot />
        </div>
    </td>
</template>
