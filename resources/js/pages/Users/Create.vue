<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ flash?: { success?: string } }>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/manage/users', {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="إضافة مستخدم" />

    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 p-4"
        style="font-family: 'Tajawal', sans-serif"
        dir="rtl"
    >
        <link
            href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap"
            rel="stylesheet"
        />

        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-white">إضافة مستخدم</h1>
                <p class="mt-2 text-slate-400">إنشاء حساب جديد</p>
            </div>

            <!-- Success message -->
            <div
                v-if="props.flash?.success"
                class="mb-4 rounded-lg bg-emerald-500/20 px-4 py-3 text-sm text-emerald-300"
            >
                {{ props.flash.success }}
            </div>

            <div class="rounded-2xl bg-slate-800/60 p-8 shadow-2xl backdrop-blur">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Name -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-300">
                            الاسم
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            class="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-3 text-white placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            :class="{ 'border-red-500': form.errors.name }"
                            placeholder="الاسم الكامل"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-300">
                            البريد الإلكتروني
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-3 text-white placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            :class="{ 'border-red-500': form.errors.email }"
                            placeholder="example@email.com"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-300">
                            كلمة المرور
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-3 text-white placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            :class="{ 'border-red-500': form.errors.password }"
                            placeholder="8 أحرف على الأقل"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-400">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Password confirmation -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-300">
                            تأكيد كلمة المرور
                        </label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-3 text-white placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            placeholder="أعد كتابة كلمة المرور"
                        />
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white transition hover:bg-blue-700 active:scale-[0.98] disabled:opacity-60"
                    >
                        {{ form.processing ? 'جارٍ الحفظ...' : 'إنشاء المستخدم' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
