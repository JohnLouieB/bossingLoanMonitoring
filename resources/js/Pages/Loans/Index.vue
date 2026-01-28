<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, h, computed } from 'vue';
import { Tag, Button, Select, Descriptions, message } from 'ant-design-vue';

const DescriptionsItem = Descriptions.Item;
import { 
    SearchOutlined,
    EyeOutlined,
    PlusOutlined
} from '@ant-design/icons-vue';
import { SparklesIcon } from 'lucide-vue-next';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.isAdmin ?? false);

const props = defineProps({
    members: {
        type: Object,
        required: true,
    },
    allMembers: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    monthlyInterestPayments: {
        type: Array,
        default: () => [],
    },
    remainingBalance: {
        type: Number,
        default: 0,
    },
});

const searchInput = ref(props.filters.search || '');
const isLoanDetailModalVisible = ref(false);
const isCreateLoanModalVisible = ref(false);
const isMonthlyInterestModalVisible = ref(false);
const selectedLoan = ref(null);
const selectedMemberForLoans = ref(null);
const monthlyInterestData = ref(props.monthlyInterestPayments || []);
const remainingBalance = ref(props.remainingBalance || 0);



// Watch for prop changes
watch(() => props.monthlyInterestPayments, (newVal) => {
    if (newVal) {
        // Convert to array if it's a collection/object
        const data = Array.isArray(newVal) ? newVal : Object.values(newVal);
        monthlyInterestData.value = data;
    } else {
        monthlyInterestData.value = [];
    }
}, { deep: true, immediate: true });

watch(() => props.remainingBalance, (newVal) => {
    if (newVal !== undefined) {
        remainingBalance.value = newVal;
    }
}, { immediate: true });
const advancePaymentForm = useForm({
    amount: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
});
const loanStatusForm = useForm({
    status: '',
});
const monthlyInterestForm = useForm({
    month: null,
    year: null,
    status: '',
    payment_date: null,
});

const loanForm = useForm({
    borrower_type: 'member',
    member_id: null,
    non_member_name: '',
    amount: '',
    interest_rate: '',
    status: 'pending',
    description: '',
    notes: '',
});

// Member options for select
const memberOptions = computed(() => {
    return props.allMembers.map(member => ({
        value: member.id,
        label: `${member.first_name} ${member.last_name} (${member.email})`,
    }));
});

// Show member select only when borrower type is 'member'
const showMemberSelect = computed(() => {
    return loanForm.borrower_type === 'member';
});

// Search functionality
const handleSearch = () => {
    router.get(route('loans.index'), { search: searchInput.value }, {
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

// Handle loan selection
const handleLoanSelect = (member, loanId) => {
    const loan = member.loans.find(l => l.id === parseInt(loanId));
    if (loan) {
        selectedLoan.value = loan;
        selectedMemberForLoans.value = member;
        loanStatusForm.status = loan.status;
        loanStatusForm.clearErrors();
        // Load monthly interest data when opening loan details (this also loads remaining balance)
        loadMonthlyInterestData();
        isLoanDetailModalVisible.value = true;
    }
};

// Load monthly interest data
const loadMonthlyInterestData = () => {
    if (!selectedLoan.value) return;
    
    router.get(route('loans.show', selectedLoan.value.id), {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['monthlyInterestPayments', 'remainingBalance'],
        onSuccess: (page) => {
            // Update directly to ensure it works
            if (page.props.monthlyInterestPayments) {
                const data = Array.isArray(page.props.monthlyInterestPayments) 
                    ? page.props.monthlyInterestPayments 
                    : Object.values(page.props.monthlyInterestPayments);
                monthlyInterestData.value = data;
            }
            if (page.props.remainingBalance !== undefined) {
                remainingBalance.value = page.props.remainingBalance;
            }
        },
        onError: () => {
            message.error('Failed to load monthly interest data');
        },
    });
};

// Show monthly interest modal
const showMonthlyInterestModal = () => {
    loadMonthlyInterestData();
    isMonthlyInterestModalVisible.value = true;
};

// Update monthly interest status
const updateMonthlyInterestStatus = (month, year, currentStatus) => {
    if (!selectedLoan.value) return;
    
    const newStatus = currentStatus === 'paid' ? 'pending' : 'paid';
    
    monthlyInterestForm.month = month;
    monthlyInterestForm.year = new Date().getFullYear();
    monthlyInterestForm.status = newStatus;
    monthlyInterestForm.payment_date = newStatus === 'paid' ? new Date().toISOString().split('T')[0] : null;
    
    monthlyInterestForm.patch(route('loans.update-monthly-interest', selectedLoan.value.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            message.success('Monthly interest updated successfully');
            // Reload the monthly interest data
            loadMonthlyInterestData();
            monthlyInterestForm.reset();
        },
        onError: () => {
            message.error('Failed to update monthly interest');
        },
    });
};

// Handle advance payment
const handleAdvancePayment = () => {
    if (!selectedLoan.value) return;
    
    advancePaymentForm.post(route('loans.store-advance-payment', selectedLoan.value.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            message.success('Advance payment recorded successfully');
            advancePaymentForm.reset();
            advancePaymentForm.payment_date = new Date().toISOString().split('T')[0];
            
            // Update local state from Inertia response if available
            if (page?.props?.monthlyInterestPayments) {
                const data = Array.isArray(page.props.monthlyInterestPayments) 
                    ? page.props.monthlyInterestPayments 
                    : Object.values(page.props.monthlyInterestPayments);
                monthlyInterestData.value = data;
            }
            if (page?.props?.remainingBalance !== undefined) {
                remainingBalance.value = page.props.remainingBalance;
            }
            
            // Reload monthly interest data using the show route (GET)
            loadMonthlyInterestData();
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Get month name
const getMonthName = (month) => {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[month - 1] || '';
};

// Calculate total interest collected (sum of all paid monthly interest payments)
const interestCollected = computed(() => {
    if (!monthlyInterestData.value || monthlyInterestData.value.length === 0) {
        return 0;
    }
    
    // Sum all monthly interest payments that are marked as paid
    return monthlyInterestData.value
        .filter(payment => payment.status === 'paid')
        .reduce((total, payment) => {
            const amount = parseFloat(payment.interest_amount || 0);
            return total + amount;
        }, 0);
});

// Monthly interest table columns
const monthlyInterestColumns = [
    {
        title: 'Month',
        dataIndex: 'month',
        key: 'month',
        width: 150,
        customRender: ({ record }) => {
            return getMonthName(record.month);
        },
    },
    {
        title: 'Interest Amount',
        dataIndex: 'interest_amount',
        key: 'interest_amount',
        width: 150,
        customRender: ({ record }) => {
            return formatCurrency(record.interest_amount);
        },
    },
    {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        width: 150,
        customRender: ({ record }) => {
            return h(Tag, {
                color: record.status === 'paid' ? 'green' : 'orange'
            }, () => record.status.toUpperCase());
        },
    },
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
        title: 'Actions',
        key: 'actions',
        width: 200,
        customRender: ({ record }) => {
            if (!isAdmin.value) {
                return h(Tag, {
                    color: record.status === 'paid' ? 'green' : 'orange'
                }, () => record.status.toUpperCase());
            }
            return h('div', { style: 'display: flex; gap: 8px;' }, [
                h(Button, {
                    type: record.status === 'paid' ? 'default' : 'primary',
                    size: 'small',
                    onClick: () => updateMonthlyInterestStatus(
                        record.month,
                        record.year,
                        record.status
                    )
                }, { default: () => record.status === 'paid' ? 'Mark Pending' : 'Mark Paid' })
            ]);
        },
    },
];

// Handle status update
const handleStatusUpdate = () => {
    if (!selectedLoan.value) return;
    
    loanStatusForm.patch(route('loans.update', selectedLoan.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            message.success('Loan status updated successfully');
            // Update the selected loan status in the local data
            if (selectedLoan.value) {
                selectedLoan.value.status = loanStatusForm.status;
            }
            // Also update in the member's loans array
            if (selectedMemberForLoans.value && selectedMemberForLoans.value.loans) {
                const loanIndex = selectedMemberForLoans.value.loans.findIndex(
                    l => l.id === selectedLoan.value.id
                );
                if (loanIndex !== -1) {
                    selectedMemberForLoans.value.loans[loanIndex].status = loanStatusForm.status;
                }
            }
        },
        onError: () => {
            message.error('Failed to update loan status');
        },
    });
};

// Create Loan
const showCreateLoanModal = () => {
    loanForm.reset();
    loanForm.clearErrors();
    loanForm.borrower_type = 'member';
    loanForm.status = 'pending';
    isCreateLoanModalVisible.value = true;
};

const handleCreateLoan = () => {
    loanForm.post(route('loans.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateLoanModalVisible.value = false;
            message.success('Loan created successfully');
            loanForm.reset();
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
        currency: 'PHP'
    }).format(amount);
};

// Get status color
const getStatusColor = (status) => {
    const colors = {
        'pending': 'orange',
        'approved': 'blue',
        'rejected': 'red',
        'paid': 'green'
    };
    return colors[status] || 'default';
};

// Table columns
const columns = [
    {
        title: 'First Name',
        dataIndex: 'first_name',
        key: 'first_name',
    },
    {
        title: 'Number of Loans',
        key: 'loans_count',
        width: 200,
        customRender: ({ record }) => {
            return h('span', { style: 'font-weight: bold;' }, record.loans_count || 0);
        },
    },
    {
        title: 'Total Loan Amount',
        key: 'total_loan_amount',
        width: 200,
        customRender: ({ record }) => {
            return formatCurrency(record.total_loan_amount || 0);
        },
    },
    {
        title: 'Remaining Balance',
        key: 'total_remaining_balance',
        width: 200,
        customRender: ({ record }) => {
            // Calculate remaining balance if not provided
            let balance = record.total_remaining_balance;
            if ((balance === undefined || balance === null) && record.loans && record.loans.length > 0) {
                balance = record.loans.reduce((sum, loan) => {
                    const loanBalance = loan.balance !== undefined ? loan.balance : parseFloat(loan.amount || 0);
                    return sum + loanBalance;
                }, 0);
            }
            balance = balance ?? 0;
            
            return h('span', {
                style: `font-weight: bold; color: ${balance === 0 ? '#52c41a' : '#ff4d4f'};`
            }, formatCurrency(balance));
        },
    },
    {
        title: 'View Loans',
        key: 'view_loans',
        width: 200,
        customRender: ({ record }) => {
            if (!record.loans || record.loans.length === 0) {
                return h('span', { style: 'color: #999;' }, 'No loans');
            }

            const options = record.loans.map(loan => {
                // Use balance from database column
                const balance = loan.balance !== undefined ? loan.balance : parseFloat(loan.amount || 0);
                
                return {
                    value: loan.id,
                    label: `${formatCurrency(loan.amount)} - ${loan.status.toUpperCase()} - Balance: ${formatCurrency(balance)}`,
                };
            });

            return h(Select, {
                placeholder: 'Select a loan to view',
                style: 'width: 100%;',
                options: options,
                onSelect: (value) => handleLoanSelect(record, value),
            });
        },
    },
];
</script>

<template>
    <Head title="Loans" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Loans Management
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Search and Create Button -->
                        <div style="display: flex; justify-content: space-between; margin-bottom: 16px; gap: 16px;">
                            <a-input
                                v-model:value="searchInput"
                                placeholder="Search by member name or email..."
                                style="max-width: 400px;"
                                allow-clear
                            >
                                <template #prefix>
                                    <SearchOutlined />
                                </template>
                            </a-input>
                            <a-button v-if="isAdmin" type="primary" @click="showCreateLoanModal">
                                Create Loan
                            </a-button>
                        </div>

                        <!-- Members with Loans Table -->
                        <a-table
                            :columns="columns"
                            :data-source="members.data"
                            :pagination="{
                                current: members.current_page,
                                pageSize: members.per_page,
                                total: members.total,
                                showSizeChanger: true,
                                showTotal: (total) => `Total ${total} members with loans`,
                            }"
                            :loading="false"
                            :rowClassName="(record) => {
                                // Calculate balance same way as column
                                let balance = record.total_remaining_balance;
                                if ((balance === undefined || balance === null) && record.loans && record.loans.length > 0) {
                                    balance = record.loans.reduce((sum, loan) => {
                                        const advancePaymentsArray = loan.advance_payments || loan.advancePayments || [];
                                        const advancePaymentsTotal = advancePaymentsArray.reduce((s, ap) => s + parseFloat(ap.amount || 0), 0);
                                        const loanAmount = parseFloat(loan.amount || 0);
                                        return sum + Math.max(0, loanAmount - advancePaymentsTotal);
                                    }, 0);
                                }
                                balance = balance ?? 0;
                                return balance === 0 ? 'row-paid' : '';
                            }"
                            @change="(pagination) => {
                                router.get(route('loans.index'), {
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

        <!-- Create Loan Modal -->
        <a-modal
            v-model:open="isCreateLoanModalVisible"
            title="Create New Loan"
            ok-text="Create"
            cancel-text="Cancel"
            @ok="handleCreateLoan"
            @cancel="() => { isCreateLoanModalVisible = false; loanForm.reset(); loanForm.clearErrors(); }"
        >
            <a-form :model="loanForm" layout="vertical">
                <a-form-item
                    label="Borrower Type"
                    :validate-status="loanForm.errors.borrower_type ? 'error' : ''"
                    :help="loanForm.errors.borrower_type"
                >
                    <a-select
                        v-model:value="loanForm.borrower_type"
                        placeholder="Select borrower type"
                        @change="() => { loanForm.member_id = null; loanForm.non_member_name = ''; }"
                    >
                        <a-select-option value="member">Member</a-select-option>
                        <a-select-option value="non-member">Non-Member</a-select-option>
                    </a-select>
                </a-form-item>

                <a-form-item
                    v-if="showMemberSelect"
                    label="Select Member"
                    :validate-status="loanForm.errors.member_id ? 'error' : ''"
                    :help="loanForm.errors.member_id"
                >
                    <a-select
                        v-model:value="loanForm.member_id"
                        placeholder="Select a member"
                        show-search
                        :filter-option="(input, option) => {
                            return option.label.toLowerCase().indexOf(input.toLowerCase()) >= 0;
                        }"
                        :options="memberOptions"
                    />
                </a-form-item>

                <a-form-item
                    v-else
                    label="Non-Member Name"
                    :validate-status="loanForm.errors.non_member_name ? 'error' : ''"
                    :help="loanForm.errors.non_member_name"
                >
                    <a-input
                        v-model:value="loanForm.non_member_name"
                        placeholder="Enter non-member name"
                    />
                </a-form-item>

                <a-form-item
                    label="Loan Amount"
                    :validate-status="loanForm.errors.amount ? 'error' : ''"
                    :help="loanForm.errors.amount"
                >
                    <a-input-number
                        v-model:value="loanForm.amount"
                        placeholder="Enter loan amount"
                        :min="0"
                        :precision="2"
                        style="width: 100%;"
                    />
                </a-form-item>

                <a-form-item
                    label="Interest Rate (%)"
                    :validate-status="loanForm.errors.interest_rate ? 'error' : ''"
                    :help="loanForm.errors.interest_rate"
                >
                    <a-input-number
                        v-model:value="loanForm.interest_rate"
                        placeholder="Enter interest rate"
                        :min="0"
                        :max="100"
                        :precision="2"
                        style="width: 100%;"
                    />
                </a-form-item>

                <a-form-item
                    label="Status"
                    :validate-status="loanForm.errors.status ? 'error' : ''"
                    :help="loanForm.errors.status"
                >
                    <a-select v-model:value="loanForm.status" placeholder="Select status">
                        <a-select-option value="pending">Pending</a-select-option>
                        <a-select-option value="approved">Approved</a-select-option>
                        <a-select-option value="rejected">Rejected</a-select-option>
                    </a-select>
                </a-form-item>

                <a-form-item
                    label="Description"
                    :validate-status="loanForm.errors.description ? 'error' : ''"
                    :help="loanForm.errors.description"
                >
                    <a-textarea
                        v-model:value="loanForm.description"
                        placeholder="Enter loan description (optional)"
                        :rows="3"
                    />
                </a-form-item>

                <a-form-item
                    label="Notes"
                    :validate-status="loanForm.errors.notes ? 'error' : ''"
                    :help="loanForm.errors.notes"
                >
                    <a-textarea
                        v-model:value="loanForm.notes"
                        placeholder="Enter notes (optional)"
                        :rows="3"
                    />
                </a-form-item>
            </a-form>
        </a-modal>

        <!-- Loan Detail Modal -->
        <a-modal
            v-model:open="isLoanDetailModalVisible"
            width="600px"
            @cancel="() => { isLoanDetailModalVisible = false; selectedLoan = null; selectedMemberForLoans = null; loanStatusForm.reset(); loanStatusForm.clearErrors(); }"
        >
            <template #title>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Loan Details</span>
                    <a-button type="primary" @click="showMonthlyInterestModal" style="margin-left: 10px; margin-right: 20px;">
                        Monthly Interest
                    </a-button>
                </div>
            </template>
            <template #footer>
                <a-button @click="() => { isLoanDetailModalVisible = false; selectedLoan = null; selectedMemberForLoans = null; loanStatusForm.reset(); loanStatusForm.clearErrors(); }">
                    Close
                </a-button>
            </template>
            <div v-if="selectedLoan && selectedMemberForLoans" style="padding: 16px 0;">
                <a-descriptions :column="1" bordered>
                    <a-descriptions-item label="Member Name">
                        {{ selectedMemberForLoans.first_name }} {{ selectedMemberForLoans.last_name }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Member Email">
                        {{ selectedMemberForLoans.email }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Loan Amount">
                        <span>
                            {{ formatCurrency(selectedLoan.amount) }}
                        </span>
                    </a-descriptions-item>
                    <a-descriptions-item label="Interest Rate">
                        {{ selectedLoan.interest_rate }}%
                    </a-descriptions-item>
                    <a-descriptions-item label="Balance">
                        <span>
                            {{ formatCurrency(selectedLoan.balance !== undefined ? selectedLoan.balance : selectedLoan.amount) }}
                        </span>
                    </a-descriptions-item>
                    
                    <a-descriptions-item label="Interest Collected">
                        <span style="font-weight: bold; font-size: 16px; color: #52c41a;">
                            {{ formatCurrency(interestCollected) }}
                        </span>
                    </a-descriptions-item>
                    <a-descriptions-item label="Status">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <a-select
                                v-if="isAdmin"
                                v-model:value="loanStatusForm.status"
                                style="width: 200px;"
                                @change="handleStatusUpdate"
                                :loading="loanStatusForm.processing"
                            >
                                <a-select-option value="pending">Pending</a-select-option>
                                <a-select-option value="approved">Approved</a-select-option>
                                <a-select-option value="rejected">Rejected</a-select-option>
                            </a-select>
                            <a-tag :color="getStatusColor(loanStatusForm.status)">
                                {{ loanStatusForm.status.toUpperCase() }}
                            </a-tag>
                            <span v-if="loanStatusForm.errors.status" style="color: red; font-size: 12px;">
                                {{ loanStatusForm.errors.status }}
                            </span>
                        </div>
                    </a-descriptions-item>
                    <a-descriptions-item label="Description" v-if="selectedLoan.description">
                        {{ selectedLoan.description }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Notes" v-if="selectedLoan.notes">
                        {{ selectedLoan.notes }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Created At">
                        {{ new Date(selectedLoan.created_at).toLocaleString() }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Updated At">
                        {{ new Date(selectedLoan.updated_at).toLocaleString() }}
                    </a-descriptions-item>
                </a-descriptions>
            </div>
        </a-modal>

        <!-- Monthly Interest Modal -->
        <a-modal
            v-model:open="isMonthlyInterestModalVisible"
            title="Monthly Interest Payments"
            width="800px"
            :footer="null"
            @cancel="() => { isMonthlyInterestModalVisible = false; advancePaymentForm.reset(); advancePaymentForm.payment_date = new Date().toISOString().split('T')[0]; }"
        >
            <div v-if="selectedLoan" style="padding: 16px 0;">
                <!-- Remaining Balance and Advance Payment Section -->
                <div style="margin-bottom: 24px; padding: 16px; background: #f5f5f5; border-radius: 4px;">
                    <h3 style="margin-bottom: 16px;">Remaining Balance: {{ formatCurrency(remainingBalance) }}</h3>
                    
                    <a-form v-if="isAdmin" :model="advancePaymentForm" layout="inline" @submit.prevent="handleAdvancePayment">
                        <a-form-item
                            label="Advance Payment"
                            :validate-status="advancePaymentForm.errors.amount ? 'error' : ''"
                            :help="advancePaymentForm.errors.amount"
                        >
                            <a-input-number
                                v-model:value="advancePaymentForm.amount"
                                placeholder="Enter amount"
                                :min="0"
                                :precision="2"
                                style="width: 150px;"
                            />
                        </a-form-item>
                        <a-form-item
                            label="Payment Date"
                            :validate-status="advancePaymentForm.errors.payment_date ? 'error' : ''"
                            :help="advancePaymentForm.errors.payment_date"
                        >
                            <a-input
                                v-model:value="advancePaymentForm.payment_date"
                                type="date"
                                style="width: 150px;"
                            />
                        </a-form-item>
                        <a-form-item>
                            <a-button type="primary" html-type="submit" :loading="advancePaymentForm.processing">
                                Record Payment
                            </a-button>
                        </a-form-item>
                    </a-form>
                    <p v-else style="color: #999; font-style: italic;">View-only mode: Cannot record payments</p>
                </div>

                <!-- Monthly Interest Table -->
                <div v-if="monthlyInterestData.length === 0" style="text-align: center; padding: 20px; color: #999;">
                    No monthly interest data available. Please wait while data loads...
                </div>
                <a-table
                    v-else
                    :columns="monthlyInterestColumns"
                    :data-source="monthlyInterestData"
                    :pagination="false"
                    :loading="false"
                />
            </div>
        </a-modal>
    </AuthenticatedLayout>
</template>
