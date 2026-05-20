<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import SystemInfoForm from './Partials/SystemInfoForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    systemSettings: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profile
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

                <!-- Admin layout: 2x2 grid -->
                <div v-if="$page.props.auth.user.isAdmin" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Profile Information -->
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </div>

                    <!-- Update Password -->
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <UpdatePasswordForm />
                    </div>

                    <!-- System Info -->
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <SystemInfoForm :system-settings="systemSettings" />
                    </div>

                    <!-- Delete Account -->
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <DeleteUserForm />
                    </div>
                </div>

                <!-- Viewer layout: read-only -->
                <div v-else class="bg-white p-6 shadow sm:rounded-lg">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
                            <p class="mt-1 text-sm text-gray-600">Your account's profile information.</p>
                        </header>
                        <div class="mt-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $page.props.auth.user.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $page.props.auth.user.email }}</p>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
