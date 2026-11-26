<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/Panel/MemberController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TempPasswordFlash from '@/components/TempPasswordFlash.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { t } from '@/lib/i18n';
import { formatJalaliDateTime } from '@/lib/format';
import { index, store } from '@/routes/panel/members';
import type { Member } from '@/types';

defineProps<{
    members: Member[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'مدیریت اعضا',
                href: index(),
            },
        ],
    },
});

const createOpen = ref(false);
</script>

<template>
    <Head :title="t('members.title')" />

    <h1 class="sr-only">{{ t('members.title') }}</h1>

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                :title="t('members.title')"
                :description="t('members.description')"
            />

            <Dialog v-if="canManage" v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button data-test="add-member-button">
                        {{ t('members.addMember') }}
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="true"
                        class="space-y-5"
                        v-slot="{ errors, processing }"
                        @success="createOpen = false"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                {{ t('members.createTitle') }}
                            </DialogTitle>
                            <DialogDescription>
                                {{ t('members.createDescription') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="role">{{ t('common.role') }}</Label>
                            <select
                                id="role"
                                name="role"
                                class="border-input h-10 w-full rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="manager">
                                    {{ t('role.manager') }}
                                </option>
                                <option value="operator">
                                    {{ t('role.operator') }}
                                </option>
                            </select>
                            <InputError :message="errors.role" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">{{ t('common.name') }}</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                :placeholder="t('common.name')"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">{{ t('common.phone') }}</Label>
                            <Input
                                id="phone"
                                type="tel"
                                name="phone"
                                required
                                dir="ltr"
                                inputmode="tel"
                                :placeholder="t('auth.phonePlaceholder')"
                            />
                            <InputError :message="errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="temp_password">
                                {{ t('members.tempPassword') }}
                            </Label>
                            <Input
                                id="temp_password"
                                type="text"
                                name="temp_password"
                                dir="ltr"
                                autocomplete="off"
                                :placeholder="t('members.tempPasswordHint')"
                            />
                            <p class="text-xs leading-5 text-muted-foreground">
                                {{ t('members.tempPasswordHint') }}
                            </p>
                            <InputError :message="errors.temp_password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button">
                                    {{ t('common.cancel') }}
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="create-member-button"
                            >
                                <Spinner v-if="processing" />
                                {{ t('common.save') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <Card>
            <CardContent class="px-0 pb-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('common.name') }}</TableHead>
                            <TableHead>{{ t('common.phone') }}</TableHead>
                            <TableHead>{{ t('common.role') }}</TableHead>
                            <TableHead>{{ t('common.status') }}</TableHead>
                            <TableHead class="hidden sm:table-cell">
                                {{ t('members.lastLogin') }}
                            </TableHead>
                            <TableHead v-if="canManage" class="w-12" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="members.length === 0" :colspan="6">
                            {{ t('members.empty') }}
                        </TableEmpty>
                        <TableRow
                            v-for="member in members"
                            :key="member.id"
                        >
                            <TableCell class="font-medium">
                                {{ member.name ?? '—' }}
                            </TableCell>
                            <TableCell dir="ltr" class="text-left">
                                {{ member.phone }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">
                                    {{ t(`role.${member.role}`) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <span class="flex flex-wrap items-center gap-1.5">
                                    <Badge
                                        v-if="member.status === 'active'"
                                        class="bg-emerald-600 text-white hover:bg-emerald-600"
                                    >
                                        {{ t('common.active') }}
                                    </Badge>
                                    <Badge
                                        v-else-if="member.status === 'suspended'"
                                        variant="outline"
                                        class="text-amber-700 dark:text-amber-400"
                                    >
                                        {{ t('common.suspended') }}
                                    </Badge>
                                    <Badge v-else variant="outline">
                                        {{ t('common.revoked') }}
                                    </Badge>
                                    <Badge
                                        v-if="member.must_change_password"
                                        variant="outline"
                                        class="text-amber-700 dark:text-amber-400"
                                    >
                                        {{ t('members.mustChangePassword') }}
                                    </Badge>
                                </span>
                            </TableCell>
                            <TableCell
                                class="hidden whitespace-nowrap text-xs text-muted-foreground sm:table-cell"
                            >
                                {{ formatJalaliDateTime(member.last_login_at) }}
                            </TableCell>
                            <TableCell v-if="canManage" class="text-left">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            :disabled="member.is_self"
                                            data-test="member-actions-button"
                                        >
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        class="w-44"
                                        align="end"
                                    >
                                        <Form
                                            v-bind="MemberController.resetPassword.form(member)"
                                            v-slot="{ processing: sending }"
                                            class="contents"
                                        >
                                            <DropdownMenuItem
                                                type="submit"
                                                :disabled="sending"
                                                class="cursor-pointer"
                                            >
                                                {{ t('members.setTempPassword') }}
                                            </DropdownMenuItem>
                                        </Form>

                                        <DropdownMenuSeparator />

                                        <Form
                                            v-bind="MemberController.toggle.form(member)"
                                            v-slot="{ processing: toggling }"
                                            class="contents"
                                        >
                                            <DropdownMenuItem
                                                type="submit"
                                                :disabled="toggling"
                                                class="cursor-pointer"
                                            >
                                                {{
                                                    member.status === 'active'
                                                        ? t('members.suspend')
                                                        : t('members.activate')
                                                }}
                                            </DropdownMenuItem>
                                        </Form>

                                        <Form
                                            v-bind="MemberController.destroy.form(member)"
                                            v-slot="{ processing: removing }"
                                            class="contents"
                                        >
                                            <DropdownMenuItem
                                                type="submit"
                                                :disabled="removing"
                                                class="cursor-pointer text-destructive data-[highlighted]:text-destructive"
                                            >
                                                {{ t('members.remove') }}
                                            </DropdownMenuItem>
                                        </Form>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <TempPasswordFlash />
</template>
