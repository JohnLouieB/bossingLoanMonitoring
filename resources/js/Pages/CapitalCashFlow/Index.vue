<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, h } from 'vue';
import { Select, Table, Tag, Input, Button } from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

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
    activityTab: {
        type: String,
        default: 'transactions',
    },
    activitySearch: {
        type: String,
        default: '',
    },
    activityPage: {
        type: Number,
        default: 1,
    },
    activityData: {
        type: Object,
        default: () => ({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 10 }),
    },
    deductions: {
        type: Array,
        default: () => [],
    },
    totalDeductions: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const selectedYear = ref(props.currentYear);
const activityTabLocal = ref(props.activityTab);
const activitySearchInput = ref(props.activitySearch);
let searchTimeout = null;

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

// Sync activity tab and search from server
watch([() => props.activityTab, () => props.activitySearch], ([tab, search]) => {
    activityTabLocal.value = tab ?? 'transactions';
    activitySearchInput.value = search ?? '';
}, { immediate: true });

// Activity tab options
const activityTabOptions = [
    { value: 'transactions', label: 'Transactions' },
    { value: 'interest', label: 'Interest Collected' },
    { value: 'contributions', label: 'Monthly Contributions Collected' },
];

// Build activity query params (year + activity)
const getActivityParams = (overrides = {}) => {
    return {
        year: selectedYear.value,
        activity_tab: overrides.activity_tab ?? activityTabLocal.value,
        activity_search: overrides.activity_search !== undefined ? overrides.activity_search : activitySearchInput.value,
        activity_page: overrides.activity_page ?? 1,
    };
};

// Reload activity (tab, search, or page change)
const reloadActivity = (overrides = {}) => {
    router.get(route('capital-cash-flow.index'), getActivityParams(overrides), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle year change
const handleYearChange = (year) => {
    selectedYear.value = year;
    reloadActivity({ activity_page: 1 });
};

// Handle activity tab change
const handleActivityTabChange = (tab) => {
    activityTabLocal.value = tab;
    reloadActivity({ activity_tab: tab, activity_page: 1 });
};

// Handle activity search (debounced)
const handleActivitySearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        reloadActivity({ activity_search: activitySearchInput.value, activity_page: 1 });
    }, 350);
};

// Handle activity page change
const handleActivityPageChange = (page) => {
    reloadActivity({ activity_page: page });
};

// Deduct button: label by current month (e.g. "Deduct 15 for feb")
const deductButtonLabel = computed(() => {
    const monthAbbrev = new Date().toLocaleString('en', { month: 'short' }).toLowerCase();
    return `Deduct 15 for ${monthAbbrev}`;
});

// Submit deduction (admin only)
const deducting = ref(false);
const submitDeduction = () => {
    deducting.value = true;
    router.post(route('capital-cash-flow.deductions.store'), { year: selectedYear.value }, {
        preserveScroll: true,
        onFinish: () => { deducting.value = false; },
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
            <h2 class="text-xl font-semibold leading-tight text-slate-800">
                Capital and Cash Flow
            </h2>
        </template>

        <div class="space-y-6 sm:space-y-8 pb-8 sm:pb-12 px-2 sm:px-0">
            <div class="mx-auto max-w-6xl px-4 sm:px-4 lg:px-6">
                <!-- Year Selector -->
                <div class="mb-6 sm:mb-8 flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-slate-600">Select Year:</span>
                    <Select
                        v-model:value="selectedYear"
                        :options="yearOptions"
                        class="w-full min-w-0 sm:w-[150px]"
                        @change="handleYearChange"
                    />
                </div>

                <!-- Capital and Deductions cards side by side -->
                <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Capital summary card -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-base font-semibold text-slate-800">Capital</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-slate-600">
                                        Total Loan Balances ({{ selectedYear }}):
                                    </span>
                                    <span class="text-base font-bold text-rose-600">
                                        {{ formatCurrency(totalLoanBalances) }}
                                    </span>
                                </div>
                                <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-slate-600">
                                        Interest Collected ({{ selectedYear }}):
                                    </span>
                                    <span class="text-base font-bold text-emerald-600">
                                        {{ formatCurrency(props.totalInterestCollected) }}
                                    </span>
                                </div>
                                <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-slate-600">
                                        Contributions Collected ({{ selectedYear }}):
                                    </span>
                                    <span class="text-base font-bold text-emerald-600">
                                        {{ formatCurrency(props.totalContributionsCollected) }}
                                    </span>
                                </div>
                                <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-slate-600">
                                        Advance Payments ({{ selectedYear }}):
                                    </span>
                                    <span class="text-base font-bold text-emerald-600">
                                        {{ formatCurrency(totalAdvancePayments) }}
                                    </span>
                                </div>
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/80 px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-slate-700">Available Capital:</span>
                                        <span class="text-xl font-bold text-emerald-700">
                                            {{ formatCurrency(availableCapital) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Deductions card -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-slate-800">Deductions</h3>
                            <Button
                                v-if="isAdmin"
                                type="primary"
                                :loading="deducting"
                                @click="submitDeduction"
                            >
                                {{ deductButtonLabel }}
                            </Button>
                        </div>
                        <div class="p-6">
                            <ul
                                v-if="deductions && deductions.length > 0"
                                class="space-y-2 text-sm text-slate-700"
                            >
                                <li
                                    v-for="d in deductions"
                                    :key="d.id"
                                    class="rounded-md border border-slate-100 bg-slate-50/50 px-3 py-2"
                                >
                                    {{ d.description }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 py-8 text-center text-sm text-slate-500"
                            >
                                No deductions for {{ selectedYear }}.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Activity: Transactions / Interest Collected / Monthly Contributions (one table with filters) -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-800">Activity</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Transaction history, interest collected, and monthly contributions in one place. Use the filter and search to find a specific member.
                        </p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <span class="text-sm font-medium text-slate-600 shrink-0">Show:</span>
                                <Select
                                    :value="activityTabLocal"
                                    :options="activityTabOptions"
                                    class="flex-1 min-w-0 sm:w-[260px]"
                                    @update:value="handleActivityTabChange"
                                />
                            </div>
                            <div class="w-full sm:flex-1 sm:min-w-[200px] sm:max-w-md">
                                <Input
                                    v-model:value="activitySearchInput"
                                    placeholder="Search by member or borrower name..."
                                    allow-clear
                                    @input="handleActivitySearch"
                                >
                                    <template #prefix>
                                        <SearchOutlined />
                                    </template>
                                </Input>
                            </div>
                        </div>
                        <div
                            v-if="!activityData.data || activityData.data.length === 0"
                            class="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 py-12 text-center text-sm text-slate-500"
                        >
                            <template v-if="activityTabLocal === 'transactions'">
                                No transactions recorded for {{ selectedYear }}<template v-if="activitySearchInput"> matching "{{ activitySearchInput }}"</template>.
                            </template>
                            <template v-else-if="activityTabLocal === 'interest'">
                                No interest payments collected for {{ selectedYear }}<template v-if="activitySearchInput"> matching "{{ activitySearchInput }}"</template>.
                            </template>
                            <template v-else>
                                No contributions collected for {{ selectedYear }}<template v-if="activitySearchInput"> matching "{{ activitySearchInput }}"</template>.
                            </template>
                        </div>
                        <div v-else class="overflow-hidden rounded-lg border border-slate-200">
                            <a-table
                                :columns="activityTabLocal === 'transactions' ? transactionColumns : (activityTabLocal === 'interest' ? interestColumns : contributionColumns)"
                                :data-source="activityData.data"
                                :pagination="{
                                    total: activityData.total,
                                    current: activityData.current_page,
                                    pageSize: activityData.per_page,
                                    showSizeChanger: false,
                                    showTotal: (total) => `Total ${total} record(s)`,
                                    onChange: handleActivityPageChange,
                                }"
                                :loading="false"
                                row-key="id"
                                :scroll="{ x: 'max-content' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
