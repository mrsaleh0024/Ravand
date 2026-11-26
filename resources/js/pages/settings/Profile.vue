<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'پروفایل',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="t('nav.profile')" />

    <h1 class="sr-only">{{ t('nav.profile') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('nav.profile')"
            :description="t('settings.description')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('common.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('common.name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">{{ t('common.phone') }}</Label>
                <Input
                    id="phone"
                    type="tel"
                    dir="ltr"
                    inputmode="tel"
                    name="phone"
                    :default-value="user.phone"
                    required
                    autocomplete="username"
                    :placeholder="t('auth.phonePlaceholder')"
                />
                <p class="text-xs leading-5 text-muted-foreground">
                    {{ t('auth.phoneHint') }}
                </p>
                <InputError :message="errors.phone" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ t('common.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
