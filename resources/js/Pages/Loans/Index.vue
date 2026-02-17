<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, h, computed } from 'vue';
import { Tag, Button, Select, Descriptions, message, List, Card, Collapse } from 'ant-design-vue';

const DescriptionsItem = Descriptions.Item;
const ListItem = List.Item;
const ListItemMeta = List.Item.Meta;
const CollapsePanel = Collapse.Panel;
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
const balanceFilter = ref(props.filters.balance_filter || 'all');
const isLoanDetailModalVisible = ref(false);
const isCreateLoanModalVisible = ref(false);
const isMonthlyInterestModalVisible = ref(false);
const isDeleteLoanModalVisible = ref(false);
const isAvailableCapitalModalVisible = ref(false);
const availableCapitalAmount = ref(0);
const selectedLoan = ref(null);
const selectedMemberForLoans = ref(null);
const monthlyInterestData = ref(props.monthlyInterestPayments || []);
const remainingBalance = ref(props.remainingBalance || 0);
const advancePayments = ref([]);
const deleteConfirmationText = ref('');
const deleteConfirmationRequired = 'DELETE';



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
    year: null,
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
    interest_rate: 3, // Default to 3% for member
    status: 'pending',
    description: '',
    year: new Date().getFullYear(),
});

// Member options for select
const memberOptions = computed(() => {
    return props.allMembers.map(member => ({
        value: member.id,
        label: `${member.first_name} ${member.last_name} (${member.email})`,
    }));
});

// Generate year options (current year ± 5 years)
const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: 11 }, (_, i) => {
    const year = currentYear - 5 + i;
    return {
        value: year,
        label: year.toString(),
    };
});

// Show member select only when borrower type is 'member'
const showMemberSelect = computed(() => {
    return loanForm.borrower_type === 'member';
});

// Watch borrower_type and auto-set interest_rate
watch(() => loanForm.borrower_type, (newType) => {
    if (newType === 'member') {
        loanForm.interest_rate = 3;
    } else if (newType === 'non-member') {
        loanForm.interest_rate = 5;
    }
}, { immediate: true });

// Search functionality
const handleSearch = () => {
    router.get(route('loans.index'), { 
        search: searchInput.value,
        balance_filter: balanceFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle balance filter change
const handleBalanceFilterChange = (value) => {
    balanceFilter.value = value;
    handleSearch();
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
        loanStatusForm.year = loan.year || null; // Set to null if no year, don't auto-set to current year
        loanStatusForm.clearErrors();
        
            // Load advance payments from the selected loan (sorted by created_at, newest first)
            const loanAdvancePayments = loan.advance_payments || loan.advancePayments || [];
            const paymentsArray = Array.isArray(loanAdvancePayments) ? loanAdvancePayments : Object.values(loanAdvancePayments);
            advancePayments.value = paymentsArray.sort((a, b) => {
                // Sort by created_at (when record was created) to show latest record first
                const dateA = new Date(a.created_at || a.payment_date);
                const dateB = new Date(b.created_at || b.payment_date);
                return dateB - dateA; // Newest first
            });
        
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
            
            // Reload advance payments by fetching fresh loan data
            reloadAdvancePayments();
        },
        onError: () => {
            message.error('Failed to load monthly interest data');
        },
    });
};

// Reload advance payments from selected loan
const reloadAdvancePayments = () => {
    if (!selectedLoan.value || !selectedMemberForLoans.value) return;
    
    // Reload members to get fresh loan data
    router.reload({ 
        only: ['members'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Find the updated member and loan in the fresh data
            const updatedMember = props.members?.data?.find(m => 
                m.id === selectedMemberForLoans.value.id
            );
            
            if (updatedMember) {
                const updatedLoan = updatedMember.loans?.find(l => l.id === selectedLoan.value.id);
                if (updatedLoan) {
                    selectedLoan.value = updatedLoan;
                    selectedMemberForLoans.value = updatedMember;
                    
                    // Update advance payments list (sorted by created_at, newest first)
                    const loanAdvancePayments = updatedLoan.advance_payments || updatedLoan.advancePayments || [];
                    const paymentsArray = Array.isArray(loanAdvancePayments) ? loanAdvancePayments : Object.values(loanAdvancePayments);
                    advancePayments.value = paymentsArray.sort((a, b) => {
                        // Sort by created_at (when record was created) to show latest record first
                        const dateA = new Date(a.created_at || a.payment_date);
                        const dateB = new Date(b.created_at || b.payment_date);
                        return dateB - dateA; // Newest first
                    });
                }
            }
        }
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
    monthlyInterestForm.year = year; // Use the year from the record, not current year
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
            
            // Reload monthly interest data and advance payments (this will sort the list)
            loadMonthlyInterestData();
            
            // Also reload advance payments to ensure we have the latest data with proper sorting
            reloadAdvancePayments();
        },
        onError: () => {
            message.error('Please fix the errors in the form');
        },
    });
};

// Handle revert advance payment
const handleRevertAdvancePayment = (advancePaymentId) => {
    if (!selectedLoan.value) return;
    
    router.delete(route('loans.revert-advance-payment', [selectedLoan.value.id, advancePaymentId]), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            message.success('Advance payment reverted successfully');
            
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
            
            // Remove the reverted payment from the list immediately for instant feedback
            advancePayments.value = advancePayments.value.filter(ap => ap.id !== advancePaymentId);
            
            // Reload monthly interest data and advance payments
            loadMonthlyInterestData();
            
            // Also reload advance payments to ensure we have the latest data
            reloadAdvancePayments();
        },
        onError: () => {
            message.error('Failed to revert advance payment');
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
            // If loan status is pending, show view-only (even for admins)
            if (selectedLoan.value && selectedLoan.value.status === 'pending') {
                return h(Tag, {
                    color: record.status === 'paid' ? 'green' : 'orange'
                }, () => record.status.toUpperCase());
            }
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

// Handle year change - immediately deducts capital
const handleYearChange = () => {
    if (!selectedLoan.value) return;
    
    // Only update if year actually changed
    if (selectedLoan.value.year === loanStatusForm.year) {
        return;
    }
    
    loanStatusForm.patch(route('loans.update', selectedLoan.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            message.success('Loan year updated and capital adjusted successfully');
            // Update the selected loan year in the local data
            if (selectedLoan.value) {
                selectedLoan.value.year = loanStatusForm.year;
            }
            // Also update in the member's loans array
            if (selectedMemberForLoans.value && selectedMemberForLoans.value.loans) {
                const loanIndex = selectedMemberForLoans.value.loans.findIndex(
                    l => l.id === selectedLoan.value.id
                );
                if (loanIndex !== -1) {
                    selectedMemberForLoans.value.loans[loanIndex].year = loanStatusForm.year;
                }
            }
            // Reload monthly interest data and advance payments to show transferred records
            loadMonthlyInterestData();
        },
        onError: () => {
            message.error('Failed to update loan year');
            // Revert the year selection on error
            loanStatusForm.year = selectedLoan.value.year;
        },
    });
};

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
    loanForm.year = new Date().getFullYear();
    loanForm.interest_rate = 3; // Set initial interest rate for member
    isCreateLoanModalVisible.value = true;
};

// Delete Loan
const showDeleteLoanModal = () => {
    deleteConfirmationText.value = '';
    isDeleteLoanModalVisible.value = true;
};

const handleDeleteLoan = () => {
    if (!selectedLoan.value) return;
    
    if (deleteConfirmationText.value !== deleteConfirmationRequired) {
        message.error('Please type "DELETE" to confirm deletion');
        return;
    }
    
    router.delete(route('loans.destroy', selectedLoan.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteLoanModalVisible.value = false;
            isLoanDetailModalVisible.value = false;
            selectedLoan.value = null;
            selectedMemberForLoans.value = null;
            deleteConfirmationText.value = '';
            message.success('Loan deleted successfully and capital restored');
        },
        onError: () => {
            message.error('Failed to delete loan');
        },
    });
};

const handleCreateLoan = () => {
    loanForm.post(route('loans.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateLoanModalVisible.value = false;
            isAvailableCapitalModalVisible.value = false;
            message.success('Loan created successfully');
            loanForm.reset();
        },
        onError: (errors) => {
            // Check if available capital error
            if (errors.amount === 'Loan amount exceeds available capital' && errors.available_capital) {
                availableCapitalAmount.value = parseFloat(errors.available_capital);
                isAvailableCapitalModalVisible.value = true;
                return;
            }
            
            // Show specific validation errors
            if (errors.non_member_name) {
                message.error('Non-member name is required');
            } else if (errors.member_id) {
                message.error('Please select a member');
            } else if (errors.amount) {
                message.error('Please enter a valid loan amount');
            } else if (errors.year) {
                message.error('Please select a year');
            } else if (errors.error) {
                message.error(errors.error);
            } else {
                message.error('Please fix the errors in the form');
            }
        },
    });
};

// Handle update amount to available capital
const handleUpdateAmountToAvailableCapital = () => {
    loanForm.amount = availableCapitalAmount.value;
    isAvailableCapitalModalVisible.value = false;
    // Retry creating the loan with updated amount
    handleCreateLoan();
};

// Handle cancel available capital modal
const handleCancelAvailableCapitalModal = () => {
    isAvailableCapitalModalVisible.value = false;
    availableCapitalAmount.value = 0;
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

        <div class="py-4 sm:py-8 lg:py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <!-- Search, Filter and Create Button -->
                        <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:justify-between sm:items-center sm:flex-wrap">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-3 sm:flex-1 sm:min-w-0">
                                <a-input
                                    v-model:value="searchInput"
                                    placeholder="Search by member name or email..."
                                    class="w-full sm:max-w-[280px]"
                                    allow-clear
                                    @pressEnter="handleSearch"
                                >
                                    <template #prefix>
                                        <SearchOutlined />
                                    </template>
                                </a-input>
                                <a-select
                                    v-model:value="balanceFilter"
                                    class="w-full sm:w-[200px] shrink-0"
                                    @change="handleBalanceFilterChange"
                                >
                                    <a-select-option value="all">All Loans</a-select-option>
                                    <a-select-option value="has_balance">Has Balance</a-select-option>
                                    <a-select-option value="paid">Paid (Zero Balance)</a-select-option>
                                </a-select>
                            </div>
                            <a-button v-if="isAdmin" type="primary" @click="showCreateLoanModal" class="w-full sm:w-auto shrink-0">
                                Create Loan
                            </a-button>
                        </div>

                        <!-- Members with Loans Table (horizontal scroll on small screens) -->
                        <div class="overflow-x-auto -mx-2 sm:mx-0">
                        <a-table
                            :scroll="{ x: 'max-content' }"
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
                                    balance_filter: balanceFilter,
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
                        @change="() => { 
                            loanForm.member_id = null; 
                            loanForm.non_member_name = ''; 
                            // Auto-set interest rate based on borrower type
                            loanForm.interest_rate = loanForm.borrower_type === 'member' ? 3 : 5;
                        }"
                    >
                        <a-select-option value="member">Member</a-select-option>
                        <a-select-option value="non-member">Non-Member</a-select-option>
                    </a-select>
                </a-form-item>

                <a-form-item
                    :label="showMemberSelect ? 'Select Member' : 'Select Member as Co-Maker'"
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
                    v-if="!showMemberSelect"
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
                    :help="loanForm.errors.interest_rate || (loanForm.borrower_type === 'member' ? 'Automatically set to 3% for members' : 'Automatically set to 5% for non-members')"
                >
                    <a-input-number
                        v-model:value="loanForm.interest_rate"
                        :placeholder="loanForm.borrower_type === 'member' ? '3' : '5'"
                        :min="0"
                        :max="100"
                        :precision="2"
                        style="width: 100%;"
                    />
                </a-form-item>

                <a-form-item
                    label="Loan Year"
                    :validate-status="loanForm.errors.year ? 'error' : ''"
                    :help="loanForm.errors.year"
                >
                    <a-select
                        v-model:value="loanForm.year"
                        placeholder="Select year"
                        :options="yearOptions"
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

            </a-form>
        </a-modal>

        <!-- Loan Detail Modal -->
        <a-modal
            v-model:open="isLoanDetailModalVisible"
            width="600px"
            @cancel="() => { isLoanDetailModalVisible = false; selectedLoan = null; selectedMemberForLoans = null; loanStatusForm.reset(); loanStatusForm.clearErrors(); loanStatusForm.year = null; }"
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
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <a-button 
                        v-if="isAdmin && selectedLoan"
                        type="primary" 
                        danger 
                        @click="showDeleteLoanModal"
                    >
                        Delete Loan
                    </a-button>
                    <a-button @click="() => { isLoanDetailModalVisible = false; selectedLoan = null; selectedMemberForLoans = null; loanStatusForm.reset(); loanStatusForm.clearErrors(); }">
                        Close
                    </a-button>
                </div>
            </template>
            <div v-if="selectedLoan && selectedMemberForLoans" style="padding: 16px 0;">
                <a-descriptions :column="1" bordered>
                    <a-descriptions-item :label="selectedMemberForLoans.loans[0].non_member_name ? 'Co-Maker Name' : 'Member Name'">
                        {{ selectedMemberForLoans.first_name }} {{ selectedMemberForLoans.last_name }}
                    </a-descriptions-item>
                    <a-descriptions-item label="Non-Member Name">
                        {{ selectedMemberForLoans.loans[0].non_member_name }}
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
                    <a-descriptions-item label="Loan Year">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <a-select
                                v-if="isAdmin"
                                v-model:value="loanStatusForm.year"
                                :options="yearOptions"
                                style="width: 150px;"
                                @change="handleYearChange"
                                :loading="loanStatusForm.processing"
                                placeholder="Select year"
                            />
                            <span v-else>{{ selectedLoan.year || 'Not set' }}</span>
                            <span v-if="loanStatusForm.errors.year" style="color: red; font-size: 12px;">
                                {{ loanStatusForm.errors.year }}
                            </span>
                        </div>
                        <p v-if="isAdmin && selectedLoan.year !== loanStatusForm.year" style="color: #1890ff; font-size: 12px; margin-top: 4px;">
                            Capital will be adjusted when you select a year
                        </p>
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
                    <a-descriptions-item label="Description">
                        {{ selectedLoan.description || 'N/A' }}
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
            width="min(96vw, 960px)"
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

                <!-- Advance Payments List -->
                <div style="margin-bottom: 24px;">
                    <a-collapse :bordered="false">
                        <a-collapse-panel key="1" :header="`Recorded Advance Payments (${advancePayments.length})`">
                            <div v-if="advancePayments.length === 0" style="text-align: center; padding: 20px; color: #999; background: #f5f5f5; border-radius: 4px;">
                                No advance payments recorded yet.
                            </div>
                            <a-list
                                v-else
                                :data-source="advancePayments"
                                :pagination="false"
                                item-layout="horizontal"
                                :bordered="true"
                            >
                                <template #renderItem="{ item }">
                                    <a-list-item>
                                        <a-list-item-meta>
                                            <template #title>
                                                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                    <div>
                                                        <span style="font-weight: 600;">{{ formatCurrency(item.amount) }}</span>
                                                        <span style="color: #999; margin-left: 12px; font-size: 12px;">
                                                            {{ new Date(item.payment_date).toLocaleDateString() }}
                                                        </span>
                                                        <span v-if="item.notes" style="color: #666; margin-left: 12px; font-size: 12px;">
                                                            - {{ item.notes }}
                                                        </span>
                                                    </div>
                                                    <a-button
                                                        v-if="isAdmin"
                                                        type="primary"
                                                        danger
                                                        size="small"
                                                        @click="handleRevertAdvancePayment(item.id)"
                                                        style="margin-left: 12px;"
                                                    >
                                                        Undo Payment
                                                    </a-button>
                                                </div>
                                            </template>
                                        </a-list-item-meta>
                                    </a-list-item>
                                </template>
                            </a-list>
                        </a-collapse-panel>
                    </a-collapse>
                </div>

                <!-- Monthly Interest Table -->
                <div v-if="monthlyInterestData.length === 0" style="text-align: center; padding: 20px; color: #999;">
                    No monthly interest data available. Please wait while data loads...
                </div>
                <div v-else class="overflow-x-auto min-w-0 -mx-1">
                    <a-table
                        :columns="monthlyInterestColumns"
                        :data-source="monthlyInterestData"
                        :pagination="false"
                        :loading="false"
                        :scroll="{ x: 'max-content' }"
                    />
                </div>
            </div>
        </a-modal>

        <!-- Delete Loan Confirmation Modal -->
        <a-modal
            v-model:open="isDeleteLoanModalVisible"
            title="Delete Loan"
            ok-text="Delete"
            ok-type="danger"
            cancel-text="Cancel"
            :ok-button-props="{ disabled: deleteConfirmationText !== deleteConfirmationRequired }"
            @ok="handleDeleteLoan"
            @cancel="() => { isDeleteLoanModalVisible = false; deleteConfirmationText = ''; }"
        >
            <div v-if="selectedLoan && selectedMemberForLoans">
                <p style="margin-bottom: 16px; color: #ff4d4f; font-weight: 500;">
                    Warning: This action cannot be undone!
                </p>
                <p style="margin-bottom: 16px;">
                    You are about to delete the loan for:
                </p>
                <div style="background: #f5f5f5; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                    <p style="margin: 4px 0;"><strong>Borrower:</strong> {{ selectedMemberForLoans.first_name }} {{ selectedMemberForLoans.last_name }}</p>
                    <p style="margin: 4px 0;"><strong>Loan Amount:</strong> {{ formatCurrency(selectedLoan.amount) }}</p>
                    <p style="margin: 4px 0;" v-if="selectedLoan.year"><strong>Loan Year:</strong> {{ selectedLoan.year }}</p>
                    <p style="margin: 4px 0;"><strong>Balance:</strong> {{ formatCurrency(selectedLoan.balance !== undefined ? selectedLoan.balance : selectedLoan.amount) }}</p>
                </div>
                <p style="margin-bottom: 12px; color: #666;">
                    This will:
                </p>
                <ul style="margin-bottom: 16px; padding-left: 20px; color: #666;">
                    <li>Delete the loan and all associated records</li>
                    <li v-if="selectedLoan.year">Restore {{ formatCurrency(selectedLoan.amount) }} to the capital for year {{ selectedLoan.year }}</li>
                    <li>Delete all advance payments and monthly interest records</li>
                </ul>
                <a-form-item
                    label="Type 'DELETE' to confirm:"
                    :validate-status="deleteConfirmationText !== deleteConfirmationRequired && deleteConfirmationText.length > 0 ? 'error' : ''"
                    :help="deleteConfirmationText !== deleteConfirmationRequired && deleteConfirmationText.length > 0 ? 'Confirmation text does not match' : ''"
                >
                    <a-input
                        v-model:value="deleteConfirmationText"
                        placeholder="Type DELETE to confirm"
                        style="width: 100%;"
                    />
                </a-form-item>
            </div>
        </a-modal>

        <!-- Available Capital Warning Modal -->
        <a-modal
            v-model:open="isAvailableCapitalModalVisible"
            title="Insufficient Available Capital"
            ok-text="Update Amount"
            cancel-text="Cancel"
            ok-type="primary"
            @ok="handleUpdateAmountToAvailableCapital"
            @cancel="handleCancelAvailableCapitalModal"
        >
            <div>
                <p style="margin-bottom: 16px; color: #ff4d4f; font-weight: 500;">
                    The loan amount exceeds the available capital for the selected year.
                </p>
                <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 16px;">
                    <p style="margin: 8px 0;"><strong>Requested Amount:</strong> {{ formatCurrency(loanForm.amount) }}</p>
                    <p style="margin: 8px 0;"><strong>Available Capital:</strong> {{ formatCurrency(availableCapitalAmount) }}</p>
                    <p style="margin: 8px 0; color: #ff4d4f; font-weight: 600;">
                        <strong>Remaining Available Capital:</strong> {{ formatCurrency(availableCapitalAmount) }}
                    </p>
                </div>
                <p style="margin-bottom: 12px; color: #666;">
                    Would you like to update the loan amount to the maximum available capital?
                </p>
            </div>
        </a-modal>
    </AuthenticatedLayout>
</template>
