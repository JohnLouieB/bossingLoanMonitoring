<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, h, computed } from 'vue';
import { message, Tag, Button } from 'ant-design-vue';
import { 
    PlusOutlined, 
    EditOutlined, 
    DeleteOutlined, 
    SearchOutlined 
} from '@ant-design/icons-vue';

const props = defineProps({
    members: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const searchInput = ref(props.filters.search || '');
const isAddModalVisible = ref(false);
const isEditModalVisible = ref(false);
const isDeleteModalVisible = ref(false);
const selectedMember = ref(null);

const addForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    is_active: true,
});

const editForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    is_active: true,
});

// Search functionality
const handleSearch = () => {
    router.get(route('members.index'), { search: searchInput.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Watch for search input changes and debounce
let searchTimeout;
watch(searchInput, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 500);
});

// Add Member
const showAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    isAddModalVisible.value = true;
};

const handleAdd = () => {
    addForm.post(route('members.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isAddModalVisible.value = false;
            message.success('Member created successfully');
            addForm.reset();
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Edit Member
const showEditModal = (member) => {
    selectedMember.value = member;
    editForm.first_name = member.first_name;
    editForm.last_name = member.last_name;
    editForm.email = member.email;
    editForm.is_active = member.is_active === 1 ? true : false;
    editForm.clearErrors();
    isEditModalVisible.value = true;
};

const handleEdit = () => {
    editForm.put(route('members.update', selectedMember.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalVisible.value = false;
            selectedMember.value = null;
            message.success('Member updated successfully');
            editForm.reset();
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Delete Member
const showDeleteModal = (member) => {
    selectedMember.value = member;
    isDeleteModalVisible.value = true;
};

const handleDelete = () => {
    router.delete(route('members.destroy', selectedMember.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalVisible.value = false;
            selectedMember.value = null;
            message.success('Member deleted successfully');
        },
        onError: () => {
            message.error('Failed to delete member');
        },
    });
};

// Table columns
const columns = [
    {
        title: 'First Name',
        dataIndex: 'first_name',
        key: 'first_name',
    },
    {
        title: 'Last Name',
        dataIndex: 'last_name',
        key: 'last_name',
    },
    {
        title: 'Email',
        dataIndex: 'email',
        key: 'email',
    },
    {
        title: 'Status',
        dataIndex: 'is_active',
        key: 'is_active',
        width: 100,
        customRender: ({ record }) => {
            return h(Tag, {
                color: record.is_active ? 'green' : 'red'
            }, () => record.is_active ? 'Active' : 'Inactive');
        },
    },
    {
        title: 'Created At',
        dataIndex: 'created_at',
        key: 'created_at',
        width: 180,
        customRender: ({ record }) => {
            return new Date(record.created_at).toLocaleString();
        },
    },
    {
        title: 'Actions',
        key: 'actions',
        width: 150,
        customRender: ({ record }) => {
            if (!isAdmin.value) {
                return h('span', { style: 'color: #999;' }, 'View Only');
            }
            return h('div', { style: 'display: flex; gap: 8px;' }, [
                h(Button, {
                    type: 'primary',
                    size: 'small',
                    onClick: () => showEditModal(record)
                }, { default: () => h(EditOutlined) }),
                h(Button, {
                    type: 'primary',
                    danger: true,
                    size: 'small',
                    onClick: () => showDeleteModal(record)
                }, { default: () => h(DeleteOutlined) })
            ]);
        },
    },
];
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Members Management
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Search and Add Button -->
                        <div style="display: flex; justify-content: space-between; margin-bottom: 16px; gap: 16px;">
                            <a-input
                                v-model:value="searchInput"
                                placeholder="Search by name or email..."
                                style="max-width: 400px;"
                                allow-clear
                            >
                                <template #prefix>
                                    <SearchOutlined />
                                </template>
                            </a-input>
                            <a-button v-if="isAdmin" type="primary" @click="showAddModal">
                                Add Member
                            </a-button>
                        </div>

                        <!-- Members Table -->
                        <a-table
                            :columns="columns"
                            :data-source="members.data"
                            :pagination="{
                                current: members.current_page,
                                pageSize: members.per_page,
                                total: members.total,
                                showSizeChanger: true,
                                showTotal: (total) => `Total ${total} members`,
                            }"
                            :loading="false"
                            @change="(pagination) => {
                                router.get(route('members.index'), {
                                    page: pagination.current,
                                    per_page: pagination.pageSize,
                                    search: searchInput,
                                }, {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Member Modal -->
        <a-modal
            v-model:open="isAddModalVisible"
            title="Add New Member"
            ok-text="Create"
            cancel-text="Cancel"
            @ok="handleAdd"
            @cancel="() => { isAddModalVisible = false; addForm.reset(); addForm.clearErrors(); }"
        >
            <a-form :model="addForm" layout="vertical">
                <a-form-item
                    label="First Name"
                    :validate-status="addForm.errors.first_name ? 'error' : ''"
                    :help="addForm.errors.first_name"
                >
                    <a-input
                        v-model:value="addForm.first_name"
                        placeholder="Enter first name"
                    />
                </a-form-item>
                <a-form-item
                    label="Last Name"
                    :validate-status="addForm.errors.last_name ? 'error' : ''"
                    :help="addForm.errors.last_name"
                >
                    <a-input
                        v-model:value="addForm.last_name"
                        placeholder="Enter last name"
                    />
                </a-form-item>
                <a-form-item
                    label="Email"
                    :validate-status="addForm.errors.email ? 'error' : ''"
                    :help="addForm.errors.email"
                >
                    <a-input
                        v-model:value="addForm.email"
                        type="email"
                        placeholder="Enter email address"
                    />
                </a-form-item>
                <a-form-item label="Status">
                    <a-switch v-model:checked="addForm.is_active" />
                    <span style="margin-left: 8px;">
                        {{ addForm.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </a-form-item>
            </a-form>
        </a-modal>

        <!-- Edit Member Modal -->
        <a-modal
            v-model:open="isEditModalVisible"
            title="Edit Member"
            ok-text="Update"
            cancel-text="Cancel"
            @ok="handleEdit"
            @cancel="() => { isEditModalVisible = false; selectedMember = null; editForm.reset(); editForm.clearErrors(); }"
        >
            <a-form :model="editForm" layout="vertical">
                <a-form-item
                    label="First Name"
                    :validate-status="editForm.errors.first_name ? 'error' : ''"
                    :help="editForm.errors.first_name"
                >
                    <a-input
                        v-model:value="editForm.first_name"
                        placeholder="Enter first name"
                    />
                </a-form-item>
                <a-form-item
                    label="Last Name"
                    :validate-status="editForm.errors.last_name ? 'error' : ''"
                    :help="editForm.errors.last_name"
                >
                    <a-input
                        v-model:value="editForm.last_name"
                        placeholder="Enter last name"
                    />
                </a-form-item>
                <a-form-item
                    label="Email"
                    :validate-status="editForm.errors.email ? 'error' : ''"
                    :help="editForm.errors.email"
                >
                    <a-input
                        v-model:value="editForm.email"
                        type="email"
                        placeholder="Enter email address"
                    />
                </a-form-item>
                <a-form-item label="Status">
                    <a-switch v-model:checked="editForm.is_active" />
                    <span style="margin-left: 8px;">
                        {{ editForm.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </a-form-item>
            </a-form>
        </a-modal>

        <!-- Delete Confirmation Modal -->
        <a-modal
            v-model:open="isDeleteModalVisible"
            title="Delete Member"
            ok-text="Delete"
            ok-type="danger"
            cancel-text="Cancel"
            @ok="handleDelete"
            @cancel="() => { isDeleteModalVisible = false; selectedMember = null; }"
        >
            <p>
                Are you sure you want to delete
                <strong>
                    {{ selectedMember ? `${selectedMember.first_name} ${selectedMember.last_name}` : '' }}
                </strong>?
            </p>
            <p style="color: red; margin-top: 8px;">
                This action cannot be undone.
            </p>
        </a-modal>
    </AuthenticatedLayout>
</template>
