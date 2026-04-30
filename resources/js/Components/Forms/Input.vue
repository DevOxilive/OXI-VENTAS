<script setup>
import { ref, watch, defineProps, defineEmits, onMounted } from 'vue';
import InputLabel from './InputLabel.vue';
import PrimaryButton from '../PrimaryButton.vue';
import { ArrowPathIcon, EyeIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
/* -----------------------Definicion de Props y Emits--------------------------- */
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
/* --------------------------------Constantes----------------------------------- */
const inputValue = ref(props.modelValue);
const pattern = ref('');
const fileInput = ref(null);
const fileUrl = ref('');
const onlyLetters = "^[A-Za-zÀ-ÿ]+$";
const lettersAndSpace = "^[A-Za-zÀ-ÿ\\s]+$";
const onlyNumbers = "^[0-9]+$";
const lettersAndNumbers = "^[A-Za-zÀ-ÿ0-9]+$";
const lettersNumbersAndSpace = "^[A-Za-zÀ-ÿ0-9\\s]+$";
const email = "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"; //Por definir
/*-----------------------------Funciones y Watch-------------------------------- */
watch(inputValue, (newValue) => {
    if (props.type === 'text' && typeof newValue === 'string' && !props.notUpper) {
        emits('update:modelValue', newValue.toUpperCase());
    } else {
        emits('update:modelValue', newValue);
    }
});
watch(() => props.modelValue, (newValue) => {
    inputValue.value = newValue;
});
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        fileUrl.value = URL.createObjectURL(file);
        emits('change', event);
    }
};
const validateRegex = (event, pattern) => {
    const key = event.key;
    const regex = new RegExp(pattern);
    if (!regex.test(key)) {
        event.preventDefault();
    }
}
const fetchFile = async () => {
    if (fileUrl.value) {
        window.open(fileUrl.value, '_blank');
    } else {
        const encodedPath = encodeURIComponent(props.modelValue);
        axios.get(route('files-storage.employee.show', encodedPath), {
            responseType: 'blob'
        })
            .then((response) => {
                const fileURL = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
                window.open(fileURL, '_blank');
            })
            .catch((err) => console.log("Error al traer el archivo. Error: ", err));
    }
}
const triggerFileSelect = () => {
    fileInput.value.click();
}
const clearFileInput = () => {
    if (fileInput.value) {
        fileInput.value.value = '';
        emits('update:modelValue', null);
    }
}

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
                    pattern.value = lettersNumbersAndSpace
                    break;
            }
        }
    }
})
</script>
<template>
    <div class="mb-4">
        <InputLabel :for="id" v-if="!withoutLabel">{{ label }}: <span v-if="required" class="text-red-600">*</span>
        </InputLabel>
        <div class="flex" v-if="type == 'file'">
            <label
                class="relative inline-block w-full px-4 py-1 text-center bg-slate-200 hover:bg-violet-100 text-violet-800 hover:text-violet-700 bg-violet font-semibold rounded-full input-file cursor-pointer">
                <span class="inline-block max-w-[200px] overflow-hidden text-ellipsis whitespace-nowrap align-middle">{{
                    modelValue ? modelValue : 'Selecciona un documento' }}</span>
                <input :id="id" :name="id" :type="type" @change="handleFileChange" ref="fileInput"
                    class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer"
                    :accept="accept ? accept : ''" />
            </label>
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
        <div class="flex" v-else>
            <input v-if="type == 'date'" :id="id" :type="type" v-model="inputValue" :min="min" :max="max"
                :required="required" :disabled="disabled" :autocomplete="autocomplete"
                class="w-auto shadow shadow-sm focus:shadow-outline appearance-none border border-gray-400 rounded rounded-md focus:border-cyan-500 focus:border-2 py-2 px-3 w-full text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-cyan-500"
                :class="className" />
            <input v-else :id="id" :type="type" :placeholder="placeholder" v-model="inputValue" :minlength="min"
                :maxlength="max" :required="required" :disabled="disabled" :class="className"
                :autocomplete="autocomplete" @keypress="(event) => validateRegex(event, pattern)" class="w-auto shadow shadow-sm focus:shadow-outline appearance-none border border-gray-400 rounded rounded-md focus:border-cyan-500 focus:border-2 py-2 px-3 w-full text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-cyan-500
            " />
            <slot />
        </div>
        <span v-if="isTesting">El valor es: {{ inputValue }}</span>
        <p></p>
    </div>

</template>
