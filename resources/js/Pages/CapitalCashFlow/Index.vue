<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, h } from 'vue';
import { InputNumber, Select, Button, Card, message, Table, Tag, Collapse, Input } from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

const CollapsePanel = Collapse.Panel;

const props = defineProps({
    capital: {
        type: Number,
        default: 0,
    },
    currentYear: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    transactions: {
        type: Array,
        default: () => [],
    },
    interestPayments: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const selectedYear = ref(props.currentYear);
const interestSearchInput = ref('');

// Generate year options (current year ± 5 years)
const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: 11 }, (_, i) => {
    const year = currentYear - 5 + i;
    return {
        value: year,
        label: year.toString(),
    };
});

const capitalForm = useForm({
    capital: props.capital,
    year: props.currentYear,
});

// Watch for changes in capital prop
watch(() => props.capital, (newVal) => {
    capitalForm.capital = newVal;
}, { immediate: true });

// Watch for changes in currentYear prop
watch(() => props.currentYear, (newYear) => {
    selectedYear.value = newYear;
    capitalForm.year = newYear;
}, { immediate: true });

// Handle year change
const handleYearChange = (year) => {
    selectedYear.value = year;
    router.get(route('capital-cash-flow.index'), { 
        year: year,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle capital update
const handleUpdateCapital = () => {
    capitalForm.patch(route('capital-cash-flow.update'), {
        preserveScroll: true,
        onSuccess: () => {
            message.success('Capital updated successfully');
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount || 0);
};

// Transaction table columns
const transactionColumns = [
    {
        title: 'Date',
        dataIndex: 'created_at',
        key: 'created_at',
        width: 180,
        customRender: ({ record }) => {
            return new Date(record.created_at).toLocaleString();
        },
    },
    {
        title: 'Type',
        dataIndex: 'type',
        key: 'type',
        width: 120,
        customRender: ({ record }) => {
            const color = record.type === 'deduction' ? 'red' : record.type === 'addition' ? 'green' : 'blue';
            const label = record.type.charAt(0).toUpperCase() + record.type.slice(1);
            return h(Tag, { color }, () => label);
        },
    },
    {
        title: 'Amount',
        dataIndex: 'amount',
        key: 'amount',
        width: 150,
        customRender: ({ record }) => {
            const isDeduction = record.type === 'deduction';
            return h('span', {
                style: {
                    color: isDeduction ? '#ff4d4f' : '#52c41a',
                    fontWeight: 'bold',
                }
            }, (isDeduction ? '-' : '+') + formatCurrency(record.amount));
        },
    },
    {
        title: 'Description',
        dataIndex: 'description',
        key: 'description',
    },
];

// Get month name
const getMonthName = (month) => {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[month - 1] || '';
};

// Filter interest payments by borrower name
const filteredInterestPayments = computed(() => {
    if (!props.interestPayments || props.interestPayments.length === 0) {
        return [];
    }
    
    if (!interestSearchInput.value.trim()) {
        return props.interestPayments;
    }
    
    const searchTerm = interestSearchInput.value.toLowerCase().trim();
    return props.interestPayments.filter(payment => {
        return payment.borrower_name.toLowerCase().includes(searchTerm);
    });
});

// Calculate total interest collected (from filtered results)
const totalInterestCollected = computed(() => {
    if (!filteredInterestPayments.value || filteredInterestPayments.value.length === 0) {
        return 0;
    }
    return filteredInterestPayments.value.reduce((total, payment) => {
        return total + parseFloat(payment.interest_amount || 0);
    }, 0);
});

// Interest collected table columns
const interestColumns = [
    {
        title: 'Payment Date',
        dataIndex: 'payment_date',
        key: 'payment_date',
        width: 150,
        customRender: ({ record }) => {
            return record.payment_date 
                ? new Date(record.payment_date).toLocaleDateString()
                : '-';
        },
    },
    {
        title: 'Month',
        dataIndex: 'month',
        key: 'month',
        width: 120,
        customRender: ({ record }) => {
            return getMonthName(record.month);
        },
    },
    {
        title: 'Borrower Name',
        dataIndex: 'borrower_name',
        key: 'borrower_name',
        width: 200,
    },
    {
        title: 'Interest Amount',
        dataIndex: 'interest_amount',
        key: 'interest_amount',
        width: 150,
        customRender: ({ record }) => {
            return h('span', {
                style: {
                    color: '#52c41a',
                    fontWeight: 'bold',
                }
            }, formatCurrency(record.interest_amount));
        },
    },
];

</script>

<template>
    <Head title="Capital and Cash Flow" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Capital and Cash Flow
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Year Selector -->
                        <div style="margin-bottom: 24px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 500;">Select Year:</span>
                                <Select
                                    v-model:value="selectedYear"
                                    :options="yearOptions"
                                    style="width: 150px;"
                                    @change="handleYearChange"
                                />
                            </div>
                        </div>

                        <!-- Capital Card -->
                        <Card title="Capital" style="max-width: 600px;">
                            <div style="margin-bottom: 16px;">
                                <p style="color: #666; margin-bottom: 12px;">
                                    Enter the capital amount for the year {{ selectedYear }}.
                                </p>
                                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                                            Capital Amount
                                        </label>
                                        <InputNumber
                                            v-model:value="capitalForm.capital"
                                            :min="0"
                                            :precision="2"
                                            style="width: 100%;"
                                            :formatter="(value) => `₱ ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                                            :parser="(value) => value.replace(/₱\s?|(,*)/g, '')"
                                            :disabled="!isAdmin"
                                        />
                                        <div v-if="capitalForm.errors.capital" style="color: #ff4d4f; margin-top: 4px; font-size: 14px;">
                                            {{ capitalForm.errors.capital }}
                                        </div>
                                    </div>
                                    <div v-if="isAdmin" style="margin-top: 32px;">
                                        <Button 
                                            type="primary" 
                                            @click="handleUpdateCapital"
                                            :loading="capitalForm.processing"
                                        >
                                            Update Capital
                                        </Button>
                                    </div>
                                </div>
                                <div style="margin-top: 16px; padding: 12px; background-color: #f0f0f0; border-radius: 4px;">
                                    <span style="font-weight: 500;">Current Capital: </span>
                                    <span style="font-weight: bold; color: #1890ff; font-size: 18px;">
                                        {{ formatCurrency(capital) }}
                                    </span>
                                </div>
                            </div>
                        </Card>

                        <!-- Transaction History Card (Loan Disbursements Only) -->
                        <Card title="Transaction History (Loan Disbursements)" style="margin-top: 24px;">
                            <p style="color: #666; margin-bottom: 12px; font-size: 14px;">
                                Records of capital deductions when loans are disbursed.
                            </p>
                            <div v-if="!transactions || transactions.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                No loan disbursements recorded for {{ selectedYear }}.
                            </div>
                            <a-table
                                v-else
                                :columns="transactionColumns"
                                :data-source="transactions"
                                :pagination="false"
                                :loading="false"
                                row-key="id"
                            />
                        </Card>

                        <!-- Interest Collected Card (Collapsible) -->
                        <Card style="margin-top: 24px;">
                            <Collapse :bordered="false">
                                <CollapsePanel 
                                    key="1" 
                                    :header="`Interest Collected - Total: ${formatCurrency(totalInterestCollected)}`"
                                >
                                    <div style="margin-bottom: 16px; padding: 12px; background-color: #f0f9ff; border-radius: 4px; border-left: 4px solid #1890ff;">
                                        <span style="font-weight: 500;">Total Interest Collected for {{ selectedYear }}: </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 18px;">
                                            {{ formatCurrency(totalInterestCollected) }}
                                        </span>
                                    </div>
                                    
                                    <!-- Search Bar -->
                                    <div style="margin-bottom: 16px;">
                                        <Input
                                            v-model:value="interestSearchInput"
                                            placeholder="Search by borrower name..."
                                            style="max-width: 400px;"
                                            allow-clear
                                        >
                                            <template #prefix>
                                                <SearchOutlined />
                                            </template>
                                        </Input>
                                    </div>
                                    
                                    <div v-if="!props.interestPayments || props.interestPayments.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                        No interest payments collected for {{ selectedYear }}.
                                    </div>
                                    <div v-else-if="filteredInterestPayments.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                        No interest payments found matching "{{ interestSearchInput }}".
                                    </div>
                                    <a-table
                                        v-else
                                        :columns="interestColumns"
                                        :data-source="filteredInterestPayments"
                                        :pagination="false"
                                        :loading="false"
                                        row-key="id"
                                        :scroll="{ y: 500, x: 'max-content' }"
                                    />
                                </CollapsePanel>
                            </Collapse>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
