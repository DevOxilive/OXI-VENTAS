<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { t } from '@/i18n/es'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
})
</script>

<template>
    <Head title="Bienvenida" />

    <main class="flex min-h-screen items-center justify-center bg-background px-6 py-12 text-text">
        <section class="w-full max-w-xl rounded-[2rem] border border-secondary bg-background p-8 text-center shadow-xl">
            <img
                src="/icons/super-kay-source.png"
                alt="Logotipo de Super Kay"
                class="mx-auto h-28 w-28 rounded-3xl object-cover"
            >

            <h1 class="mt-6 text-3xl font-black">
                Bienvenido a Super Kay
            </h1>

            <p class="mt-3 text-sm opacity-70">
                Administra ventas, inventario, sucursales y operaciones desde un solo lugar.
            </p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="rounded-xl border border-primary bg-primary px-5 py-3 font-semibold text-white transition hover:brightness-110"
                >
                    {{ t('auth.login') }}
                </Link>

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-xl border border-secondary bg-secondary px-5 py-3 font-semibold transition hover:brightness-95"
                >
                    Crear cuenta
                </Link>
            </div>
        </section>
    </main>
</template>
