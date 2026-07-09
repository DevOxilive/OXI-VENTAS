<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        email: data.email + '@oxilive.com.mx',
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-[linear-gradient(135deg,#fff8ef_0%,#fffdf9_45%,#f7f4ee_100%)]">
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-10 h-56 w-56 rounded-full bg-[#f59e0b]/12 blur-3xl" />
            <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-[#7c2d12]/10 blur-3xl" />
            <div class="absolute bottom-0 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-[#facc15]/10 blur-3xl" />
        </div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center text-center">
                    <div class="rounded-[2.25rem] bg-white/90 p-4 shadow-lg shadow-amber-900/10 ring-1 ring-amber-100 sm:p-5">
                        <img
                            src="/icons/super-kay-source.png"
                            alt="Super Kay Logo"
                            class="h-28 w-28 rounded-[1.7rem] object-cover sm:h-32 sm:w-32"
                        />
                    </div>
                </div>

                <div class="rounded-[2rem] border border-amber-100/80 bg-white/95 p-5 shadow-2xl shadow-amber-950/10 ring-1 ring-white sm:p-8">
                    <div class="mb-6 flex flex-col items-center text-center">
                        <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Iniciar Sesion</h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Ingresa tus credenciales para continuar
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-center text-sm text-amber-800"
                    >
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Usuario
                            </label>
                            <div class="flex overflow-hidden rounded-2xl border border-slate-200 bg-white transition focus-within:border-amber-300 focus-within:ring-2 focus-within:ring-amber-200">
                                <input
                                    v-model="form.email"
                                    type="text"
                                    placeholder="usuario"
                                    class="w-full border-0 bg-transparent px-4 py-3 text-base text-slate-800 outline-none sm:text-lg"
                                    required
                                    autofocus
                                />

                                <span class="hidden items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500 sm:flex">
                                    @oxilive.com.mx
                                </span>
                            </div>

                            <p class="mt-2 text-xs text-slate-500 sm:hidden">
                                Se agregara automaticamente @oxilive.com.mx
                            </p>

                            <p v-if="form.errors.email" class="mt-2 text-sm text-red-500">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Contrasena
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Contrasena"
                                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-200 sm:text-lg"
                                required
                            />

                            <p v-if="form.errors.password" class="mt-2 text-sm text-red-500">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-2xl bg-gradient-to-r from-[#8a4b16] via-[#c27a1f] to-[#f0a62b] px-4 py-3 text-base font-bold text-white shadow-lg shadow-amber-900/25 transition hover:brightness-105 sm:py-4 sm:text-lg"
                            :class="{ 'cursor-not-allowed opacity-50': form.processing }"
                        >
                            Iniciar Sesion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
