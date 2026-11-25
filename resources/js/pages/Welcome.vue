<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const stack = [
    { label: 'بک‌اند', value: 'Laravel 13 · PHP 8.4' },
    { label: 'دیتابیس', value: 'MySQL 8.4 · utf8mb4' },
    { label: 'فرانت‌اند', value: 'Vue 3 · TypeScript · Inertia' },
    { label: 'رابط کاربری', value: 'Tailwind 4 · shadcn-vue' },
];
</script>

<template>
    <Head title="رَوَند" />

    <div dir="rtl" class="min-h-screen bg-white text-neutral-900">
        <header class="border-b border-neutral-200">
            <div
                class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-5 py-4 sm:px-8"
            >
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-semibold tracking-tight"
                        >رَوَند</span
                    >
                    <span class="text-xs text-neutral-500">Ravand</span>
                </div>
                <span
                    class="rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-600"
                >
                    نسخه MVP — فاز ۰۰
                </span>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl px-5 py-12 sm:px-8 sm:py-20">
            <p class="text-sm text-neutral-500">
                باشگاه مشتریان و CRM فروشندگان شبکه‌های اجتماعی
            </p>

            <h1
                class="mt-3 max-w-2xl text-2xl leading-relaxed font-semibold sm:text-4xl sm:leading-tight"
            >
                مشتریان، سفارش‌ها و سلیقه‌ها را در یک جای مرتب نگه دارید.
            </h1>

            <p
                class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 sm:text-base sm:leading-8"
            >
                رَوَند برای فروشندگانی ساخته می‌شود که فروش را در اینستاگرام،
                واتساپ و تلگرام انجام می‌دهند. داده‌ها ساخت‌یافته نگهداری می‌شوند تا
                بعداً قابل تحلیل و خروجی گرفتن باشند.
            </p>

            <div class="mt-10 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="item in stack"
                    :key="item.label"
                    class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3"
                >
                    <div class="text-xs text-neutral-500">{{ item.label }}</div>
                    <div class="mt-1 text-sm text-neutral-900" dir="ltr">
                        {{ item.value }}
                    </div>
                </div>
            </div>

            <div
                class="mt-10 rounded-lg border border-neutral-200 px-4 py-4 sm:px-5"
            >
                <div class="text-sm font-medium">وضعیت این فاز</div>
                <p class="mt-2 text-sm leading-7 text-neutral-600">
                    زیرساخت پایه آماده است: اتصال به دیتابیس، migration اولیه و
                    build فرانت‌اند. صفحات محصول، احراز هویت و پوسته کامل
                    راست‌به‌چپ در فازهای بعدی اضافه می‌شوند.
                </p>
            </div>
        </main>

        <footer class="border-t border-neutral-200">
            <div
                class="mx-auto flex w-full max-w-5xl flex-col gap-1 px-5 py-5 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between sm:px-8"
            >
                <span>Ravand — MVP</span>
                <span dir="ltr">Laravel 13 · Vue 3</span>
            </div>
        </footer>
    </div>
</template>
