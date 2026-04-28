<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

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
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Log in" />
    <div class="min-h-screen flex flex-col lg:flex-row overflow-hidden"> <!-- LADO IZQUIERDO: FONDO VERDE CON LOGO -->
        <div
            class="md:w-1/2 bg-gradient-to-br from-green-400 to-green-600 flex flex-col justify-center items-center text-white p-8">
            <img src="@/img/OXILIVE.ico" alt="Logo" class="w-24 mb-4" />
            <h1 class="text-3xl md:text-4xl font-bold text-center">OXI-VENTAS</h1>
        </div>
        <!-- LADO DERECHO: FORMULARIO -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-white">
            <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col items-center mb-6">
                    <div class="flex justify-center"> <img src="@/img/user.jpg" alt="Encabezado"
                            class="w-20 sm:w-40 md:w-48" /> </div>
                    <h2 class="text-xl md:text-2xl font-bold mt-4">Inciar Sesión</h2>
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-green-600 text-center"> {{ status }} </div>
                <!-- Formulario -->
                <form @submit.prevent="submit" class="space-y-6"> <!-- Email -->
                    <div class="relative"> <input id="email" v-model="form.email" type="email"
                            placeholder="Correo electrónico"
                            class="w-full pl-12 pr-4 py-4 text-lg rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-400"
                            required autofocus autocomplete="username" /> <span
                            class="absolute left-4 top-4 text-gray-400 text-xl"></span>
                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div> <!-- Password -->
                    <div class="relative"> <input id="password" v-model="form.password" type="password"
                            placeholder="Contraseña"
                            class="w-full pl-12 pr-4 py-4 text-lg rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-400"
                            required autocomplete="current-password" /> <span
                            class="absolute left-4 top-4 text-gray-400 text-xl"></span>
                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                    </div> <!-- Recordarme -->
                    <div class="flex items-center"> <input id="remember" type="checkbox" v-model="form.remember"
                            class="h-5 w-5 text-green-500 border-gray-300 rounded" />
                        <label for="remember" class="ml-2 text-gray-600">Recuérdame</label>
                    </div> <!-- Botón --> <button type="submit" :disabled="form.processing"
                        class="w-full py-4 text-lg rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold transition-transform transform hover:scale-105 shadow-md"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"> Iniciar Sesión </button>
                </form> <!-- Olvidaste tu contraseña -->
                <p class="text-sm text-center text-gray-600 mt-4">
                    <Link v-if="canResetPassword" :href="route('password.request')"
                        class="text-green-500 font-bold hover:underline"> ¿Olvidaste tu contraseña? </Link>
                </p> <!-- Registro -->
                <p class="text-sm text-center text-gray-600"> ¿No tienes cuenta? <a href="/register"
                        class="text-green-500 font-bold hover:underline">Regístrate</a> </p>
            </div>
        </div>
    </div>
</template>
