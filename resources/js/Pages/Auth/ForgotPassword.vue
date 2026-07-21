<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { t } from '@/i18n/es';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>

    <Head :title="t('auth.forgotPassword')" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
        <!-- Card centrada -->
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col items-center mb-6">
                <img src="@/img/OXILIVE.ico" alt="Logo" class="w-20 mb-4" />
                <h2 class="text-xl font-bold text-center">Recuperar Contraseña</h2>
                <p class="text-center text-gray-600 mt-2">
                    Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
                </p>
            </div>

            <!-- Status -->
            <div v-if="status" class="mb-4 font-medium text-sm text-center text-gray-700 bg-gray-100 p-3 rounded">
                {{ status }}
            </div>

            <!-- Formulario -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <input id="email" v-model="form.email" type="email" placeholder="Correo electrónico"
                        class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring focus:ring-gray-300"
                        required autofocus autocomplete="username" />
                    <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                </div>
                <button type="submit" :disabled="form.processing"
                    class="w-full py-3 rounded-lg text-white font-semibold bg-gradient-to-r from-green-400 to-green-600 hover:from-green-500 hover:to-green-700 transition transform hover:scale-105 shadow-md"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                    Enviar enlace de recuperación
                </button>

                

            </form>

            <p class="text-sm text-center text-gray-500 mt-4">
                <Link href="/login" class="hover:underline">Volver a iniciar sesión</Link>
            </p>
        </div>
    </div>
</template>
