<script setup>
import { ref, watch, defineProps, defineEmits, onMounted } from 'vue';
import InputLabel from './InputLabel.vue';
import PrimaryButton from '../PrimaryButton.vue';
import { ArrowPathIcon, EyeIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

/* ----------------------- Definición de Props y Emits ----------------------- */
const props = defineProps({
    modelValue: {
        type: [String, Number, null, Array],
        required: false
    },
    className: String,
    id: [String, Number],
    regex: String,
    min: String,
    max: String,
    label: {
        type: String,
        default: 'Input de texto'
    },
    type: {
        type: String,
        default: 'text'
    },
    placeholder: {
        type: String,
        default: 'Ingresa un texto'
    },
    autocomplete: {
        type: String,
        default: 'off'
    },
    accept: String,
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    isTesting: {
        type: Boolean,
        default: false,
    },
    notUpper: {
        type: Boolean,
        default: false,
    },
    withoutLabel: {
        type: Boolean,
        default: false
    }
});
const emits = defineEmits(['update:modelValue', 'change']);

/* ------------------------------ Constantes ------------------------------- */
const inputValue = ref(props.modelValue);
const pattern = ref('');
const fileInput = ref(null);
const fileUrl = ref('');

// Expresiones regulares predefinidas
const onlyLetters = "^[A-Za-zÀ-ÿ]+$";
const lettersAndSpace = "^[A-Za-zÀ-ÿ\\s]+$";
const onlyNumbers = "^[0-9]+$";
const lettersAndNumbers = "^[A-Za-zÀ-ÿ0-9]+$";
const lettersNumbersAndSpace = "^[A-Za-zÀ-ÿ0-9\\s]+$";
const email = "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"; // Por definir

/* ------------------------------ Funciones y Watch ------------------------------ */

// Monitorea los cambios en inputValue y actualiza modelValue
watch(inputValue, (newValue) => {
    if (props.type === 'text' && typeof newValue === 'string' && !props.notUpper) {
        emits('update:modelValue', newValue.toUpperCase());
    } else {
        emits('update:modelValue', newValue);
    }
});

// Monitorea modelValue para sincronizar el valor del input
watch(() => props.modelValue, (newValue) => {
    inputValue.value = newValue;
});

// Maneja el cambio de archivo
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        fileUrl.value = URL.createObjectURL(file);
        emits('change', event);
    }
};

// Valida si la tecla presionada cumple con la expresión regular
const validateRegex = (event, pattern) => {
    const key = event.key;
    const regex = new RegExp(pattern);
    if (!regex.test(key)) {
        event.preventDefault();
    }
};

// Abre el archivo almacenado
const fetchFile = async () => {
    if (fileUrl.value) {
        window.open(fileUrl.value, '_blank');
    } else {
        const path = props.modelValue;
        const extension = path.split('.').pop().toLowerCase();
        const encodedPath = encodeURIComponent(path);

        try {
            const response = await axios.get(route('files-storage.patient.show', encodedPath), {
                responseType: 'blob'
            });

            let fileURL;
            if (extension === 'pdf') {
                fileURL = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
                window.open(fileURL, '_blank'); // Abre el PDF en una nueva pestaña
            } else if (['jpg', 'jpeg', 'png'].includes(extension)) {
                fileURL = window.URL.createObjectURL(new Blob([response.data]));
                const imgWindow = window.open(); // Abre una nueva ventana
                imgWindow.document.write(`<img src="${fileURL}" style="max-width:100%;"/>`); // Muestra la imagen
            } else {
                console.log("Tipo de archivo no soportado para visualización.");
            }
        } catch (err) {
            console.log("Error al traer el archivo. Error: ", err);
        }
    }
};


// Activa la selección de archivo
const triggerFileSelect = () => {
    fileInput.value.click();
};

// Borra el archivo seleccionado
const clearFileInput = () => {
    if (fileInput.value) {
        fileInput.value.value = '';
        emits('update:modelValue', null);
    }
};

// Establece el patrón de validación basado en la propiedad regex
onMounted(() => {
    if (props.regex) {
        if (props.type == 'email') {
            pattern.value = email;
        } else {
            switch (props.regex) {
                case 'only-letters':
                    pattern.value = onlyLetters;
                    break;
                case 'only-numbers':
                    pattern.value = onlyNumbers;
                    break;
                case 'letters-and-numbers':
                    pattern.value = lettersAndNumbers;
                    break;
                case 'letters-and-space':
                    pattern.value = lettersAndSpace;
                    break;
                case 'all':
                    pattern.value = lettersNumbersAndSpace;
                    break;
            }
        }
    }
});
</script>

<template>
    <div class="mb-4">
        <!-- Condición para mostrar etiqueta -->
        <InputLabel :for="id" v-if="!withoutLabel">{{ label }}: <span v-if="required" class="text-red-600">*</span></InputLabel>
        
        <!-- Sección para el input de tipo 'file' -->
        <div class="flex" v-if="type === 'file'">
            <label class="relative inline-block w-full px-4 py-1 text-center bg-slate-200 hover:bg-violet-100 text-violet-800 hover:text-violet-700 bg-violet font-semibold rounded-full input-file cursor-pointer">
                <span class="inline-block max-w-[200px] overflow-hidden text-ellipsis whitespace-nowrap align-middle">
                    {{ modelValue ? modelValue : 'Selecciona un documento' }}
                </span>
                <input :id="id" :name="id" type="file" ref="fileInput" @change="handleFileChange" 
                       :accept="accept || '.png,.jpg,.jpeg,.pdf'" class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer" />
            </label>

            <!-- Botones para manejar archivo -->
            <template v-if="!modelValue">
                <PrimaryButton class="ml-3" @click.prevent="triggerFileSelect">
                    <PlusIcon class="w-4 h-4 stroke-white stroke-2" />
                </PrimaryButton>
            </template>
            <template v-else>
                <PrimaryButton class="ml-3" @click.prevent="fetchFile">
                    <EyeIcon class="w-4 h-4 stroke-white stroke-2" />
                </PrimaryButton>
                <PrimaryButton class="ml-3" @click.prevent="triggerFileSelect">
                    <ArrowPathIcon class="w-4 h-4 stroke-white stroke-2" />
                </PrimaryButton>
                <PrimaryButton class="ml-3" @click.prevent="clearFileInput">
                    <TrashIcon class="w-4 h-4 stroke-white stroke-2" />
                </PrimaryButton>
            </template>
        </div>

        <!-- Sección para el input de otros tipos -->
        <div class="flex" v-else>
            <input v-if="type === 'date'" :id="id" type="date" v-model="inputValue" :min="min" :max="max"
                   :required="required" :disabled="disabled" :autocomplete="autocomplete" class="w-full py-2 px-3 rounded-md shadow focus:outline-none focus:ring-cyan-500" />
            <input v-else :id="id" :type="type" :placeholder="placeholder" v-model="inputValue" :minlength="min"
                   :maxlength="max" :required="required" :disabled="disabled" :class="className"
                   :autocomplete="autocomplete" @keypress="(event) => validateRegex(event, pattern)" class="w-full py-2 px-3 rounded-md shadow focus:outline-none focus:ring-cyan-500" />
            <slot />
        </div>

        <!-- Mostrar el valor si isTesting es true -->
        <span v-if="isTesting">El valor es: {{ inputValue }}</span>
    </div>
</template>
