<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import FormSection from '@/Components/Cards/FormSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import { SuccessAlert } from '@/Components/Modales/UniversalActionModal';
import { t } from '@/i18n/es';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoFileInput();
            SuccessAlert({
                title: t('profile.updated.title'),
                message: t('profile.updated.message'),
            });
        },
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            {{ t('profile.information.title') }}
        </template>

        <template #description>
            {{ t('profile.information.description') }}
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <label for="photo" class="mb-1 block text-sm font-semibold text-slate-700">
                    {{ t('profile.photo') }}
                </label>

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-2">
                    <img :src="user.profile_photo_url" :alt="user.name" class="rounded-full size-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span
                        class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <AppButton class="mt-2 me-2" variant="secondary" type="button" @click.prevent="selectNewPhoto">
                    {{ t('profile.photo.select') }}
                </AppButton>

                <AppButton
                    v-if="user.profile_photo_path"
                    variant="secondary"
                    type="button"
                    class="mt-2"
                    @click.prevent="deletePhoto"
                >
                    {{ t('profile.photo.remove') }}
                </AppButton>

                <p v-if="form.errors.photo" class="mt-2 text-xs text-red-500">
                    {{ form.errors.photo }}
                </p>
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputField
                    v-model="form.name"
                    :label="t('common.name')"
                    field="name"
                    type="text"
                    :error="form.errors.name"
                    required
                    autocomplete="name"
                />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <InputField
                    v-model="form.email"
                    :label="t('common.email')"
                    field="email"
                    type="email"
                    :error="form.errors.email"
                    required
                    autocomplete="username"
                />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2">
                        {{ t('profile.email.unverified') }}

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click.prevent="sendEmailVerification"
                        >
                            {{ t('profile.email.resend') }}
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                        {{ t('profile.email.resent') }}
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <AppButton variant="primary" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ t('common.save') }}
            </AppButton>
        </template>
    </FormSection>
</template>
