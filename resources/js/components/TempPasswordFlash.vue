<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import type { TempPasswordFlash } from '@/types/auth';

/**
 * One-time display of an issued temporary password. The server flashes the
 * value exactly once; copying is possible, re-rendering after navigation is
 * impossible (never stored in plaintext server-side).
 */
const page = usePage();

const flash = computed(
    // Inertia v3 exposes session flash at the page level (not inside props).
    () => page.flash?.tempPassword as TempPasswordFlash | undefined,
);

const visible = computed(() => flash.value !== undefined);
const copied = ref(false);

const copy = async () => {
    if (flash.value === undefined) {
        return;
    }

    try {
        await navigator.clipboard.writeText(flash.value.password);
        copied.value = true;
    } catch {
        copied.value = false;
    }
};
</script>

<template>
    <Dialog :open="visible" @update:open="() => {}">
        <DialogContent
            class="sm:max-w-md"
            :show-close-button="false"
            @interact-outside="(e: Event) => e.preventDefault()"
            @escape-key-down="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <div
                    class="mx-auto mb-2 flex size-11 items-center justify-center rounded-full border bg-muted/40"
                >
                    <KeyRound class="size-5 text-muted-foreground" />
                </div>
                <DialogTitle class="text-center">
                    {{ t('tempPassword.title') }}
                </DialogTitle>
                <DialogDescription class="text-center leading-6">
                    {{ t('tempPassword.description') }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="flash"
                class="space-y-3 rounded-md border bg-muted/30 p-4 text-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">
                        {{ t('tempPassword.user') }}
                    </span>
                    <span class="font-medium">{{ flash.user }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">
                        {{ t('tempPassword.phone') }}
                    </span>
                    <span dir="ltr" class="font-medium">{{ flash.phone }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">
                        {{ t('tempPassword.password') }}
                    </span>
                    <span
                        dir="ltr"
                        class="rounded bg-background px-2 py-1 font-mono font-semibold tracking-wide"
                    >
                        {{ flash.password }}
                    </span>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-center">
                <Button variant="outline" @click="copy">
                    {{
                        copied
                            ? t('common.copied')
                            : t('common.copy')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
