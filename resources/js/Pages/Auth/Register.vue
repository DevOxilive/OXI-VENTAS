<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    terms: false,
});


// Generar correo automático
watch(() => form.name, (newName) => {
    if (newName) {
        const formatted = newName
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '.');

        form.email = `${formatted}@oxilive.com.mx`;
    }
});

// Enviar formulario
const submit = () => {
    form.post('/register', {
        onSuccess: () => {
            form.reset();
        },
        onError: (errors) => {
            console.log(errors);
        }
    });
};

// Limpiar formulario manual
const limpiar = () => {
    form.reset();
};
const roles = usePage().props.roles;
</script>

<template>

    <Head title="Registro" />

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- IZQUIERDA -->
        <div
            class="md:w-1/2 bg-gradient-to-br from-green-400 to-green-600 flex flex-col justify-center items-center text-white p-8">
            <img src="@/img/OXILIVE.ico" alt="Logo" class="w-24 mb-4" />
            <h1 class="text-3xl md:text-4xl font-bold text-center">OXI-VENTAS</h1>
        </div>

        <!-- DERECHA -->
        <div class="flex-1 flex justify-center items-center p-6">
            <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">

                <!-- MENSAJE -->
                <div v-if="$page.props.flash?.success"
                    class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-center">
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-col items-center mb-6">
                    <img src="@/img/user.jpg" class="w-20 sm:w-40 md:w-48" />
                    <h2 class="text-xl md:text-2xl font-bold mt-4">Crea tu cuenta</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-4">

                    <!-- NOMBRE -->
                    <div>
                        <input type="text" placeholder="Nombre completo" v-model="form.name"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                            required />
                        <p v-if="form.errors.name" class="text-red-500 text-sm">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- ROL -->
             <select v-model="form.role"
    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
    required>

    <option value="">Seleccionar rol</option>

    <option v-for="rol in roles" :key="rol.id" :value="rol.name">
        {{ rol.name }}
    </option>

</select>

                    <!-- EMAIL -->
                 <div>
    <input type="email" v-model="form.email"
        placeholder="correo"
        class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500" />

    <p v-if="form.errors.email" class="text-red-500 text-sm">
        {{ form.errors.email }}
    </p>
</div>

                    <!-- PASSWORD -->
                    <div>
                        <input type="password" placeholder="Contraseña" v-model="form.password"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                            required />
                        <p v-if="form.errors.password" class="text-red-500 text-sm">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- CONFIRMAR -->
                    <div>
                        <input type="password" placeholder="Confirmar contraseña"
                            v-model="form.password_confirmation"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                            required />
                    </div>

                    <!-- BOTONES -->
                    <div class="flex gap-2">
                        <button type="submit"
                            class="w-1/2 bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition"
                            :disabled="form.processing">
                            Registrarme
                        </button>

                        <button type="button"
                            @click="limpiar"
                            class="w-1/2 bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                            Limpiar
                        </button>
                    </div>

                </form>

                <!-- LOGIN -->
                <p class="mt-4 text-center text-gray-600">
                    ¿Ya tienes cuenta?
                    <Link :href="route('login')" class="text-green-500 hover:underline">
                        Inicia sesión
                    </Link>
                </p>

            </div>
        </div>
    </div>
</template>