<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    systemSettings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    monthly_server_amount: props.systemSettings.monthly_server_amount ?? '',
    yearly_domain_amount: props.systemSettings.yearly_domain_amount ?? '',
    export_csv_email: props.systemSettings.export_csv_email ?? '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">System Info</h2>
            <p class="mt-1 text-sm text-gray-600">
                Manage system-level configuration and reporting settings.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('system-settings.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="monthly_server_amount" value="Monthly Server Amount" />
                <TextInput
                    id="monthly_server_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    v-model="form.monthly_server_amount"
                    placeholder="0.00"
                />
                <InputError class="mt-2" :message="form.errors.monthly_server_amount" />
            </div>

            <div>
                <InputLabel for="yearly_domain_amount" value="Yearly Domain Amount" />
                <TextInput
                    id="yearly_domain_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    v-model="form.yearly_domain_amount"
                    placeholder="0.00"
                />
                <InputError class="mt-2" :message="form.errors.yearly_domain_amount" />
            </div>

            <div>
                <InputLabel for="export_csv_email" value="Export CSV to Email" />
                <TextInput
                    id="export_csv_email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.export_csv_email"
                    placeholder="email@example.com"
                    autocomplete="email"
                />
                <InputError class="mt-2" :message="form.errors.export_csv_email" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
