<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, h, computed } from 'vue';
import { Tag, Input, InputNumber, Modal, Button, Select, message } from 'ant-design-vue';
import { SearchOutlined, EditOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    members: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    currentYear: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const searchInput = ref(props.filters.search || '');
const selectedYear = ref(props.currentYear);
const isEditAmountModalVisible = ref(false);

// Generate year options (current year ± 5 years)
const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: 11 }, (_, i) => {
    const year = currentYear - 5 + i;
    return {
        value: year,
        label: year.toString(),
    };
});

// Get the current amount from the first member (assuming all members have the same amount)
const currentAmount = computed(() => {
    if (props.members?.data && props.members.data.length > 0) {
        return props.members.data[0].contribution_amount || 0;
    }
    return 0;
});

const amountForm = useForm({
    amount: currentAmount.value,
    year: props.currentYear,
});

// Watch for changes in currentAmount to update the form
watch(currentAmount, (newVal) => {
    amountForm.amount = newVal;
});

// Watch for changes in currentYear prop
watch(() => props.currentYear, (newYear) => {
    selectedYear.value = newYear;
}, { immediate: true });

const statusForm = useForm({
    member_id: null,
    month: null,
    year: props.currentYear,
    status: 'pending',
});

// Month abbreviations
const monthAbbreviations = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount || 0);
};

// Get status color
const getStatusColor = (status) => {
    return status === 'paid' ? 'success' : 'default';
};

// Handle year change
const handleYearChange = (year) => {
    selectedYear.value = year;
    router.get(route('monthly-contributions.index'), { 
        search: searchInput.value,
        year: year,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Search functionality
const handleSearch = () => {
    router.get(route('monthly-contributions.index'), { 
        search: searchInput.value,
        year: selectedYear.value,
    }, {
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

// Handle update amount for all members
const handleUpdateAmount = () => {
    amountForm.patch(route('monthly-contributions.update-all-amounts'), {
        preserveScroll: true,
        onSuccess: () => {
            isEditAmountModalVisible.value = false;
            message.success('Contribution amount updated for all members successfully');
            amountForm.year = selectedYear.value;
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Show edit amount modal
const showEditAmountModal = () => {
    amountForm.amount = currentAmount.value;
    amountForm.year = selectedYear.value;
    amountForm.clearErrors();
    isEditAmountModalVisible.value = true;
};

// Handle status toggle
const handleStatusToggle = (member, month) => {
    const currentStatus = member.monthly_status?.[month]?.status || 'pending';
    const newStatus = currentStatus === 'paid' ? 'pending' : 'paid';
    
    statusForm.member_id = member.id;
    statusForm.month = month;
    statusForm.year = selectedYear.value;
    statusForm.status = newStatus;
    
    statusForm.patch(route('monthly-contributions.update-status', member.id), {
        preserveScroll: true,
        onSuccess: () => {
            message.success(`Status updated to ${newStatus}`);
            statusForm.reset();
            statusForm.year = selectedYear.value;
        },
        onError: () => {
            message.error('Failed to update status');
            statusForm.reset();
            statusForm.year = selectedYear.value;
        },
    });
};

// Table columns
const columns = computed(() => {
    const baseColumns = [
        {
            title: 'Member Name',
            key: 'member_name',
            width: 200,
            fixed: 'left',
            customRender: ({ record }) => {
                return h('span', `${record.first_name} ${record.last_name}`);
            },
        },
        {
            title: 'Amount',
            key: 'amount',
            width: 150,
            fixed: 'left',
            customRender: ({ record }) => {
                return formatCurrency(record.contribution_amount || 0);
            },
        },
    ];

    // Add month columns
    const monthColumns = monthAbbreviations.map((abbr, index) => ({
        title: abbr,
        key: `month_${index + 1}`,
        width: 80,
        align: 'center',
        customRender: ({ record }) => {
            const monthStatus = record.monthly_status?.[index + 1];
            const status = monthStatus?.status || 'pending';
            const monthNumber = index + 1;
            
            const tagElement = h(Tag, {
                color: getStatusColor(status),
            }, () => status === 'paid' ? 'Paid' : 'Pending');
            
            if (!isAdmin.value) {
                return tagElement;
            }
            
            return h('div', {
                style: {
                    cursor: 'pointer',
                    userSelect: 'none',
                    display: 'inline-block',
                },
                onClick: () => handleStatusToggle(record, monthNumber),
            }, [tagElement]);
        },
    }));

    return [...baseColumns, ...monthColumns];
});
</script>

<template>
    <Head title="Monthly Contributions" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Monthly Contributions
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Top Controls -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 16px; flex-wrap: wrap;">
                            <!-- Search Bar and Year Selector -->
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <Input
                                    v-model:value="searchInput"
                                    placeholder="Search by member name or email..."
                                    style="max-width: 400px;"
                                    allow-clear
                                >
                                    <template #prefix>
                                        <SearchOutlined />
                                    </template>
                                </Input>
                                
                                <!-- Year Selector -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-weight: 500;">Year:</span>
                                    <Select
                                        v-model:value="selectedYear"
                                        :options="yearOptions"
                                        style="width: 120px;"
                                        @change="handleYearChange"
                                    />
                                </div>
                            </div>
                            
                            <!-- Edit Amount Button -->
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 500;">Contribution Amount:</span>
                                <span style="font-weight: bold; color: #1890ff;">{{ formatCurrency(currentAmount) }}</span>
                                <Button v-if="isAdmin" type="primary" size="small" @click="showEditAmountModal">
                                    Edit Amount
                                </Button>
                            </div>
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
                            :scroll="{ x: 1200 }"
                            @change="(pagination) => {
                                router.get(route('monthly-contributions.index'), {
                                    page: pagination.current,
                                    per_page: pagination.pageSize,
                                    search: searchInput,
                                    year: selectedYear,
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

        <!-- Edit Amount Modal -->
        <a-modal
            v-model:open="isEditAmountModalVisible"
            title="Edit Contribution Amount (All Members)"
            ok-text="Update All"
            cancel-text="Cancel"
            @ok="handleUpdateAmount"
            @cancel="() => { isEditAmountModalVisible = false; amountForm.reset(); amountForm.clearErrors(); amountForm.amount = currentAmount; }"
        >
            <div>
                <p style="margin-bottom: 16px; color: #666;">
                    This will update the contribution amount for <strong>all members</strong> for the year {{ selectedYear }}.
                </p>
                <a-form-item
                    label="Amount"
                    :validate-status="amountForm.errors.amount ? 'error' : ''"
                    :help="amountForm.errors.amount"
                >
                    <a-input-number
                        v-model:value="amountForm.amount"
                        :min="0"
                        :precision="2"
                        style="width: 100%;"
                        :formatter="(value) => `₱ ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                        :parser="(value) => value.replace(/₱\s?|(,*)/g, '')"
                    />
                </a-form-item>
            </div>
        </a-modal>
    </AuthenticatedLayout>
</template>
