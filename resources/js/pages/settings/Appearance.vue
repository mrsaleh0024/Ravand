<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { t } from '@/lib/i18n';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'ظاهر',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('nav.appearance')" />

    <h1 class="sr-only">{{ t('nav.appearance') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('nav.appearance')"
            description="حالت نمایش برنامه را تنظیم کنید"
        />
        <AppearanceTabs />
    </div>
</template>
