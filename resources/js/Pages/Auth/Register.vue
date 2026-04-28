<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>

    <Head title="Registro" />

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Columna izquierda -->
        <div
            class="md:w-1/2 bg-gradient-to-br from-green-400 to-green-600 flex flex-col justify-center items-center text-white p-8">
            <img src="@/img/OXILIVE.ico" alt="Logo" class="w-24 mb-4" />
            <h1 class="text-3xl md:text-4xl font-bold text-center">OXI-VENTAS</h1>
        </div>

        <!-- Columna derecha -->
        <div class="flex-1 flex justify-center items-center p-6">
            <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col items-center mb-6">
                    <div class="flex justify-center">
                        <img src="@/img/user.jpg" alt="Encabezado" class="w-20 sm:w-40 md:w-48" />
                    </div>
                    <h2 class="text-xl md:text-2xl font-bold mt-4">Crea tu cuenta</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Nombre completo -->
                    <div class="relative">
                        <input type="text" placeholder="Nombre completo" v-model="form.name"
                            class="w-full p-3 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            required />
                    </div>

                    <div class="relative">
                        <input type="email" placeholder="Correo electrónico" v-model="form.email"
                            class="w-full p-3 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            required />
                    </div>

                    <div class="relative">
                        <input type="password" placeholder="Contraseña" v-model="form.password"
                            class="w-full p-3 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            required />
                    </div>

                    <div class="relative">
                        <input type="password" placeholder="Confirmar contraseña" v-model="form.password_confirmation"
                            class="w-full p-3 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            required />
                    </div>

                    <!-- Botón -->
                    <button type="submit"
                        class="w-full bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition"
                        :disabled="form.processing">
                        Registrarme
                    </button>
                </form>

                <p class="mt-4 text-center text-gray-600 text-sm md:text-base">
                    ¿Ya tienes cuenta?
                    <Link :href="route('login')" class="text-green-500 hover:underline">
                        Inicia sesión
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
