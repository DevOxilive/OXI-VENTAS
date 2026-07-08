<script setup>
import { ref, watch, defineProps, defineEmits, onMounted } from 'vue';
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue';

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

const inputValue = ref(props.modelValue);
const pattern = ref('');
const fileInput = ref(null);
const fileUrl = ref('');

const onlyLetters = '^[A-Za-zÀ-ÿ]+$';
const lettersAndSpace = '^[A-Za-zÀ-ÿ\\s]+$';
const onlyNumbers = '^[0-9]+$';
const lettersAndNumbers = '^[A-Za-zÀ-ÿ0-9]+$';
const lettersNumbersAndSpace = '^[A-Za-zÀ-ÿ0-9\\s]+$';
const email = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$';
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

const validateRegex = (event, regexPattern) => {
    const key = event.key;
    const regex = new RegExp(regexPattern);

    if (!regex.test(key)) {
        event.preventDefault();
    }
};

const fetchFile = async () => {
    if (fileUrl.value) {
        window.open(fileUrl.value, '_blank');
        return;
    }

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
            window.open(fileURL, '_blank');
            return;
        }

        if (['jpg', 'jpeg', 'png'].includes(extension)) {
            fileURL = window.URL.createObjectURL(new Blob([response.data]));
            const imgWindow = window.open();
            imgWindow.document.write(`<img src="${fileURL}" style="max-width:100%;"/>`);
            return;
        }

        console.log('Tipo de archivo no soportado para visualización.');
    } catch (err) {
        console.log('Error al traer el archivo. Error: ', err);
    }
};

const triggerFileSelect = () => {
    fileInput.value.click();
};

const clearFileInput = () => {
    if (fileInput.value) {
        fileInput.value.value = '';
        emits('update:modelValue', null);
    }
};

onMounted(() => {
    if (!props.regex) return;

    if (props.type === 'email') {
        pattern.value = email;
        return;
    }

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
});
</script>

<template>
    <div class="mb-4">
        <label v-if="!withoutLabel" :for="id" class="mb-1 block text-sm font-semibold text-slate-700">
            {{ label }}: <span v-if="required" class="text-red-600">*</span>
        </label>

        <div v-if="type === 'file'" class="flex">
            <label class="relative inline-block w-full cursor-pointer rounded-full bg-slate-200 px-4 py-1 text-center font-semibold text-violet-800 hover:bg-violet-100 hover:text-violet-700">
                <span class="inline-block max-w-[200px] overflow-hidden text-ellipsis whitespace-nowrap align-middle">
                    {{ modelValue ? modelValue : 'Selecciona un documento' }}
                </span>
                <input
                    :id="id"
                    :name="id"
                    type="file"
                    ref="fileInput"
                    class="absolute left-0 top-0 h-full w-full cursor-pointer opacity-0"
                    :accept="accept || '.png,.jpg,.jpeg,.pdf'"
                    @change="handleFileChange"
                />
            </label>

            <template v-if="!modelValue">
                <ActionIconButton class="ml-3" icon="add" title="Seleccionar archivo" variant="amber" @click.prevent="triggerFileSelect" />
            </template>
            <template v-else>
                <ActionIconButton class="ml-3" icon="visibility" title="Ver archivo" variant="slate" @click.prevent="fetchFile" />
                <ActionIconButton class="ml-3" icon="sync" title="Reemplazar archivo" variant="amber" @click.prevent="triggerFileSelect" />
                <ActionIconButton class="ml-3" icon="delete" title="Eliminar archivo" variant="red" @click.prevent="clearFileInput" />
            </template>
        </div>

        <div v-else class="flex">
            <input
                v-if="type === 'date'"
                :id="id"
                type="date"
                v-model="inputValue"
                :min="min"
                :max="max"
                :required="required"
                :disabled="disabled"
                :autocomplete="autocomplete"
                class="w-full rounded-md px-3 py-2 shadow focus:outline-none focus:ring-cyan-500"
            />
            <input
                v-else
                :id="id"
                :type="type"
                :placeholder="placeholder"
                v-model="inputValue"
                :minlength="min"
                :maxlength="max"
                :required="required"
                :disabled="disabled"
                :class="className"
                :autocomplete="autocomplete"
                class="w-full rounded-md px-3 py-2 shadow focus:outline-none focus:ring-cyan-500"
                @keypress="(event) => validateRegex(event, pattern)"
            />
            <slot />
        </div>

        <span v-if="isTesting">El valor es: {{ inputValue }}</span>
    </div>
</template>
