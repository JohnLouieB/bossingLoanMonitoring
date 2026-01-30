<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, h } from 'vue';
import { Select, Card, message, Table, Tag, Collapse, Input } from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

const CollapsePanel = Collapse.Panel;

const props = defineProps({
    initialCapital: {
        type: Number,
        default: 0,
    },
    baseCapital: {
        type: Number,
        default: 0,
    },
    availableCapital: {
        type: Number,
        default: 0,
    },
    totalLoanBalances: {
        type: Number,
        default: 0,
    },
    totalInterestCollected: {
        type: Number,
        default: 0,
    },
    totalContributionsCollected: {
        type: Number,
        default: 0,
    },
    totalAdvancePayments: {
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
    contributions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const selectedYear = ref(props.currentYear);
const interestSearchInput = ref('');
const contributionSearchInput = ref('');

// Generate year options (current year ± 5 years)
const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: 11 }, (_, i) => {
    const year = currentYear - 5 + i;
    return {
        value: year,
        label: year.toString(),
    };
});

// Watch for changes in currentYear prop
watch(() => props.currentYear, (newYear) => {
    selectedYear.value = newYear;
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
            let color = 'blue';
            let label = record.type.charAt(0).toUpperCase() + record.type.slice(1);
            
            if (record.type === 'deduction') {
                color = 'red';
                label = 'Loan Disbursement';
            } else if (record.type === 'addition') {
                if (record.description && record.description.includes('Advance payment')) {
                    color = 'green';
                    label = 'Advance Payment';
                } else if (record.description && record.description.includes('Interest payment')) {
                    color = 'green';
                    label = 'Interest';
                } else if (record.description && record.description.includes('Monthly contribution')) {
                    color = 'green';
                    label = 'Contribution';
                } else {
                    color = 'green';
                    label = 'Addition';
                }
            }
            
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

// Filter contributions by member name
const filteredContributions = computed(() => {
    if (!props.contributions || props.contributions.length === 0) {
        return [];
    }
    
    if (!contributionSearchInput.value.trim()) {
        return props.contributions;
    }
    
    const searchTerm = contributionSearchInput.value.toLowerCase().trim();
    return props.contributions.filter(contribution => {
        return contribution.member_name.toLowerCase().includes(searchTerm);
    });
});

// Calculate total contributions collected (from filtered results)
const totalContributionsCollected = computed(() => {
    if (!filteredContributions.value || filteredContributions.value.length === 0) {
        return 0;
    }
    return filteredContributions.value.reduce((total, contribution) => {
        return total + parseFloat(contribution.amount || 0);
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

// Contributions table columns
const contributionColumns = [
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
        title: 'Member Name',
        dataIndex: 'member_name',
        key: 'member_name',
        width: 200,
    },
    {
        title: 'Amount',
        dataIndex: 'amount',
        key: 'amount',
        width: 150,
        customRender: ({ record }) => {
            return h('span', {
                style: {
                    color: '#52c41a',
                    fontWeight: 'bold',
                }
            }, formatCurrency(record.amount));
        },
    },
    {
        title: 'Recorded Date',
        dataIndex: 'created_at',
        key: 'created_at',
        width: 180,
        customRender: ({ record }) => {
            return new Date(record.created_at).toLocaleString();
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
                                    <div style="padding: 12px; background-color: #f0f0f0; border-radius: 4px;">
                                    <div style="margin-bottom: 8px;">
                                        <span style="font-weight: 500;">Total Loan Balances ({{ selectedYear }}): </span>
                                        <span style="font-weight: bold; color: #ff4d4f; font-size: 16px;">
                                            {{ formatCurrency(totalLoanBalances) }}
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 8px; padding-top: 8px; border-top: 1px solid #d9d9d9;">
                                        <span style="font-weight: 500;">Interest Collected ({{ selectedYear }}): </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 16px;">
                                            {{ formatCurrency(totalInterestCollected) }}
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 8px; padding-top: 8px; border-top: 1px solid #d9d9d9;">
                                        <span style="font-weight: 500;">Contributions Collected ({{ selectedYear }}): </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 16px;">
                                            {{ formatCurrency(totalContributionsCollected) }}
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 8px; padding-top: 8px; border-top: 1px solid #d9d9d9;">
                                        <span style="font-weight: 500;">Advance Payments ({{ selectedYear }}): </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 16px;">
                                            {{ formatCurrency(totalAdvancePayments) }}
                                        </span>
                                    </div>
                                    <div style="padding-top: 8px; border-top: 1px solid #d9d9d9; background-color: #f6ffed; padding: 12px; border-radius: 4px; margin-top: 8px;">
                                        <span style="font-weight: 500;">Available Capital: </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 20px;">
                                            {{ formatCurrency(availableCapital) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Card>

                        <!-- Transaction History Card -->
                        <Card title="Transaction History" style="margin-top: 24px;">
                            <p style="color: #666; margin-bottom: 12px; font-size: 14px;">
                                Records of all capital transactions: loan disbursements (deductions) and advance payments (additions).
                            </p>
                            <div v-if="!transactions || transactions.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                No transactions recorded for {{ selectedYear }}.
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

                        <!-- Money Collected from Contributions Card (Collapsible) -->
                        <Card style="margin-top: 24px;">
                            <Collapse :bordered="false">
                                <CollapsePanel 
                                    key="2" 
                                    :header="`Money Collected from Contributions - Total: ${formatCurrency(totalContributionsCollected)}`"
                                >
                                    <div style="margin-bottom: 16px; padding: 12px; background-color: #f0f9ff; border-radius: 4px; border-left: 4px solid #1890ff;">
                                        <span style="font-weight: 500;">Total Contributions Collected for {{ selectedYear }}: </span>
                                        <span style="font-weight: bold; color: #52c41a; font-size: 18px;">
                                            {{ formatCurrency(totalContributionsCollected) }}
                                        </span>
                                    </div>
                                    
                                    <!-- Search Bar -->
                                    <div style="margin-bottom: 16px;">
                                        <Input
                                            v-model:value="contributionSearchInput"
                                            placeholder="Search by member name..."
                                            style="max-width: 400px;"
                                            allow-clear
                                        >
                                            <template #prefix>
                                                <SearchOutlined />
                                            </template>
                                        </Input>
                                    </div>
                                    
                                    <div v-if="!props.contributions || props.contributions.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                        No contributions collected for {{ selectedYear }}.
                                    </div>
                                    <div v-else-if="filteredContributions.length === 0" style="text-align: center; padding: 40px; color: #999;">
                                        No contributions found matching "{{ contributionSearchInput }}".
                                    </div>
                                    <a-table
                                        v-else
                                        :columns="contributionColumns"
                                        :data-source="filteredContributions"
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
