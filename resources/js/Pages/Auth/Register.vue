<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputField from '@/Components/Forms/InputField.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    branch_id: '',
    terms: false,
});
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

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

const branches = usePage().props.branches;
</script>

<template>
    <Head title="Registro" />

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- IZQUIERDA -->
        <div
            class="md:w-1/2 bg-gradient-to-br from-green-400 to-green-600 flex flex-col justify-center items-center text-white p-8"
        >
            <img src="/icons/super-kay-navidad.png" alt="Logotipo navideño de Super Kay" class="w-24 mb-4 rounded-3xl" />
            <h1 class="text-3xl md:text-4xl font-bold text-center">Super-Kay</h1>
        </div>

        <!-- DERECHA -->
        <div class="flex-1 flex justify-center items-center p-6">
            <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">
                <!-- MENSAJE -->
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-center"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-col items-center mb-6">
                    <img src="@/img/user.jpg" class="w-20 sm:w-40 md:w-48" />
                    <h2 class="text-xl md:text-2xl font-bold mt-4">
                        Crea tu cuenta
                    </h2>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- NOMBRE -->
                    <InputField
                        v-model="form.name"
                        label="Nombre completo"
                        field="name"
                        placeholder="Nombre completo"
                        :error="form.errors.name"
                        :show-counter="false"
                        hide-label
                        required
                    />

                    <!-- BRANCH -->
                    <div>
                        <GlobalSelect
                            v-model="form.branch_id"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                            required
                        >
                            <option value="">
                                Seleccionar sucursal del usuario
                            </option>

                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </GlobalSelect>

                        <p v-if="form.errors.branch_id" class="text-red-500 text-sm">
                            {{ form.errors.branch_id }}
                        </p>
                    </div>

                    <!-- EMAIL -->
                    <div>
                        <input
                            type="email"
                            v-model="form.email"
                            placeholder="correo"
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                        />

                        <p v-if="form.errors.email" class="text-red-500 text-sm">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- PASSWORD -->
                    <div>
                        <div class="relative">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Contraseña"
                                v-model="form.password"
                                class="w-full p-3 pr-12 border rounded-lg focus:ring-2 focus:ring-green-500"
                                required
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-gray-500 hover:bg-green-50 hover:text-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                @click="showPassword = !showPassword"
                            >
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        </div>

                        <p v-if="form.errors.password" class="text-red-500 text-sm">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- CONFIRMAR -->
                    <div>
                        <div class="relative">
                            <input
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                placeholder="Confirmar contraseña"
                                v-model="form.password_confirmation"
                                class="w-full p-3 pr-12 border rounded-lg focus:ring-2 focus:ring-green-500"
                                required
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-gray-500 hover:bg-green-50 hover:text-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                                :aria-label="showPasswordConfirmation ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                :title="showPasswordConfirmation ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                            >
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ showPasswordConfirmation ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- BOTONES -->
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="w-1/2 bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition"
                            :disabled="form.processing"
                        >
                            Registrarme
                        </button>

                        <button
                            type="button"
                            @click="limpiar"
                            class="w-1/2 bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition"
                        >
                            Limpiar
                        </button>
                    </div>
                </form>

                <!-- LOGIN -->
                <p class="mt-4 text-center text-gray-600">
                    ¿Ya tienes cuenta?

                    <Link
                        :href="route('login')"
                        class="text-green-500 hover:underline"
                    >
                        Inicia sesión
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
