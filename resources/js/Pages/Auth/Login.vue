<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
    sucursales: Array,
    sucursales: Array,
});

const form = useForm({
    email: '',
    password: '',
    sucursal_id: '',
    
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

    <div class="min-h-screen flex flex-col lg:flex-row overflow-hidden">

        <!-- IZQUIERDA -->
        <div
            class="md:w-1/2 bg-gradient-to-br from-green-400 to-green-600 flex flex-col justify-center items-center text-white p-8">
            <img src="@/img/OXILIVE.ico" alt="Logo" class="w-24 mb-4" />
            <h1 class="text-3xl md:text-4xl font-bold text-center">OXI-VENTAS</h1>
        </div>

        <!-- DERECHA -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-white">
            <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">

                <div class="flex flex-col items-center mb-6">
                    <img src="@/img/user.jpg" class="w-20 sm:w-40 md:w-48" />
                    <h2 class="text-xl md:text-2xl font-bold mt-4">Iniciar Sesión</h2>
                </div>

                <!-- MENSAJE -->
                <div v-if="status" class="mb-4 text-sm text-green-600 text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- EMAIL -->
                    <div>
                        <div class="flex">
                            <input
                                v-model="form.email"
                                type="text"
                                placeholder="usuario"
                                class="w-full py-4 px-4 text-lg border border-gray-300 rounded-l-xl focus:outline-none focus:ring-2 focus:ring-green-400"
                                required
                                autofocus
                            />

                            <span class="px-3 flex items-center bg-gray-100 border border-l-0 rounded-r-xl text-gray-500">
                                @oxilive.com.mx
                            </span>
                        </div>

                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- PASSWORD -->
                    <div>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="Contraseña"
                            class="w-full py-4 px-4 text-lg border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-400"
                            required
                        />

                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- SUCURSAL --><!-- SUCURSAL -->
<div>
    <select
        v-model="form.sucursal_id"
        class="w-full py-4 px-4 text-lg border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-400 bg-white"
    >
        <option disabled value="">
            Selecciona tu sucursal si eres vendedor
        </option>

        <option
            v-for="sucursal in sucursales"
            :key="sucursal.id"
            :value="sucursal.id"
        >
            {{ sucursal.nombre }}
        </option>
    </select>

    <p v-if="form.errors.sucursal_id" class="text-red-500 text-sm mt-1">
        {{ form.errors.sucursal_id }}
    </p>
</div>
                    <!-- BOTÓN -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-4 text-lg rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold transition-transform transform hover:scale-105 shadow-md"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    >
                        Iniciar Sesión
                    </button>

                </form>

            </div>
        </div>

    </div>
</template>