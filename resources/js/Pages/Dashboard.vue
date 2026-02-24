<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Wallet,
    TrendingUp,
    Banknote,
    AlertCircle,
    Trophy,
    ArrowUpRight,
    Percent,
} from 'lucide-vue-next';

const props = defineProps({
    availableBalanceByYear: { type: Array, default: () => [] },
    moneyReleasedByYear: { type: Array, default: () => [] },
    interestCollectedByYear: { type: Array, default: () => [] },
    membersWithUnpaidContributions: { type: Array, default: () => [] },
    topLoaners: { type: Array, default: () => [] },
    pendingLoanInterest: {
        type: Object,
        default: () => ({ member: [], non_member: [] }),
    },
});

function formatMoney(value) {
    if (value == null || Number.isNaN(value)) return '0.00';
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

// Bar chart max for scaling
const maxAvailable = computed(() =>
    Math.max(1, ...props.availableBalanceByYear.map((d) => d.value))
);
const maxReleased = computed(() =>
    Math.max(1, ...props.moneyReleasedByYear.map((d) => d.value))
);
const maxInterest = computed(() =>
    Math.max(1, ...props.interestCollectedByYear.map((d) => d.value))
);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="space-y-6 sm:space-y-8 pb-8 sm:pb-12 mx-2 sm:mx-5">
            <!-- Summary stat cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center gap-4 p-5">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600"
                        >
                            <Wallet class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">
                                Total Available Balance
                            </p>
                            <p class="truncate text-xl font-semibold text-slate-800">
                                {{ formatMoney(availableBalanceByYear.reduce((s, d) => s + d.value, 0)) }}
                            </p>
                            <p class="text-xs text-slate-400">Across all years</p>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center gap-4 p-5">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600"
                        >
                            <TrendingUp class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">
                                Total Money Released
                            </p>
                            <p class="truncate text-xl font-semibold text-slate-800">
                                {{ formatMoney(moneyReleasedByYear.reduce((s, d) => s + d.value, 0)) }}
                            </p>
                            <p class="text-xs text-slate-400">All loans by year</p>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center gap-4 p-5">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600"
                        >
                            <Banknote class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">
                                Total Interest Collected
                            </p>
                            <p class="truncate text-xl font-semibold text-slate-800">
                                {{ formatMoney(interestCollectedByYear.reduce((s, d) => s + d.value, 0)) }}
                            </p>
                            <p class="text-xs text-slate-400">Across all years</p>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center gap-4 p-5">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600"
                        >
                            <AlertCircle class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">
                                Unpaid Contributions
                            </p>
                            <p class="truncate text-xl font-semibold text-slate-800">
                                {{ membersWithUnpaidContributions.length }}
                            </p>
                            <p class="text-xs text-slate-400">Members with pending</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Available balance by year -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Available Balance by Year
                    </h3>
                    <div class="space-y-3">
                        <template
                            v-for="item in availableBalanceByYear"
                            :key="'avail-' + item.year"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-12 shrink-0 text-sm font-medium text-slate-600"
                                >{{ item.year }}</span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="h-8 overflow-hidden rounded-lg bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-lg bg-emerald-500 transition-all"
                                            :style="{
                                                width: `${(item.value / maxAvailable) * 100}%`,
                                                minWidth: item.value > 0 ? '4px' : '0',
                                            }"
                                        />
                                    </div>
                                </div>
                                <span class="w-24 shrink-0 text-right text-sm font-medium text-slate-700">
                                    {{ formatMoney(item.value) }}
                                </span>
                            </div>
                        </template>
                        <p
                            v-if="availableBalanceByYear.length === 0"
                            class="text-sm text-slate-400"
                        >
                            No data for available balance.
                        </p>
                    </div>
                </div>

                <!-- Money released by year -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Money Released by Year
                    </h3>
                    <div class="space-y-3">
                        <template
                            v-for="item in moneyReleasedByYear"
                            :key="'released-' + item.year"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-12 shrink-0 text-sm font-medium text-slate-600"
                                >{{ item.year }}</span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="h-8 overflow-hidden rounded-lg bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-lg bg-blue-500 transition-all"
                                            :style="{
                                                width: `${(item.value / maxReleased) * 100}%`,
                                                minWidth: item.value > 0 ? '4px' : '0',
                                            }"
                                        />
                                    </div>
                                </div>
                                <span class="w-24 shrink-0 text-right text-sm font-medium text-slate-700">
                                    {{ formatMoney(item.value) }}
                                </span>
                            </div>
                        </template>
                        <p
                            v-if="moneyReleasedByYear.length === 0"
                            class="text-sm text-slate-400"
                        >
                            No data for money released.
                        </p>
                    </div>
                </div>

                <!-- Interest collected by year -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Interest Collected by Year
                    </h3>
                    <div class="space-y-3">
                        <template
                            v-for="item in interestCollectedByYear"
                            :key="'interest-' + item.year"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-12 shrink-0 text-sm font-medium text-slate-600"
                                >{{ item.year }}</span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="h-8 overflow-hidden rounded-lg bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-lg bg-amber-500 transition-all"
                                            :style="{
                                                width: `${(item.value / maxInterest) * 100}%`,
                                                minWidth: item.value > 0 ? '4px' : '0',
                                            }"
                                        />
                                    </div>
                                </div>
                                <span class="w-24 shrink-0 text-right text-sm font-medium text-slate-700">
                                    {{ formatMoney(item.value) }}
                                </span>
                            </div>
                        </template>
                        <p
                            v-if="interestCollectedByYear.length === 0"
                            class="text-sm text-slate-400"
                        >
                            No data for interest collected.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pending Loan Interest (this month) -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800">
                        <Percent class="h-5 w-5 text-amber-500" />
                        Pending Loan Interest
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Loaners who have not paid interest for this month
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
                    <!-- Member borrowers -->
                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Member Borrowers
                        </h4>
                        <div class="max-h-60 overflow-auto space-y-2">
                            <Link
                                v-for="item in pendingLoanInterest.member"
                                :key="'member-' + item.loan_id"
                                :href="route('loans.index', { member_id: item.member_id })"
                                class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-2 transition hover:bg-slate-100"
                            >
                                <span class="font-medium text-slate-800">{{ item.first_name }}</span>
                                <div class="shrink-0 text-right text-sm">
                                    <span class="text-slate-600">Loan: {{ formatMoney(item.loan_amount) }}</span>
                                    <span class="ml-2 font-semibold text-amber-600">Interest: {{ formatMoney(item.interest_to_pay) }}</span>
                                </div>
                            </Link>
                            <p
                                v-if="pendingLoanInterest.member.length === 0"
                                class="py-4 text-center text-sm text-slate-400"
                            >
                                No pending interest
                            </p>
                        </div>
                    </div>
                    <!-- Non-member borrowers -->
                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Non-Member Borrowers
                        </h4>
                        <div class="max-h-60 overflow-auto space-y-2">
                            <Link
                                v-for="item in pendingLoanInterest.non_member"
                                :key="'nonmember-' + item.loan_id"
                                :href="route('loans.index', { member_id: item.member_id })"
                                class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-2 transition hover:bg-slate-100"
                            >
                                <span class="font-medium text-slate-800">{{ item.first_name }}</span>
                                <div class="shrink-0 text-right text-sm">
                                    <span class="text-slate-600">Loan: {{ formatMoney(item.loan_amount) }}</span>
                                    <span class="ml-2 font-semibold text-amber-600">Interest: {{ formatMoney(item.interest_to_pay) }}</span>
                                </div>
                            </Link>
                            <p
                                v-if="pendingLoanInterest.non_member.length === 0"
                                class="py-4 text-center text-sm text-slate-400"
                            >
                                No pending interest
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two columns: Unpaid contributions & Top loaners -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Members with unpaid monthly contributions -->
                <div
                    class="rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800">
                            <AlertCircle class="h-5 w-5 text-rose-500" />
                            Members with Unpaid Monthly Contribution
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Members who have not paid yet for their monthly contribution
                        </p>
                    </div>
                    <div class="max-h-80 overflow-auto p-4">
                        <ul class="space-y-3">
                            <li
                                v-for="member in membersWithUnpaidContributions"
                                :key="member.id"
                                class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800">
                                        {{ member.name }}
                                    </p>
                                    <p class="text-sm text-slate-500">
                                        {{ member.email || '—' }}
                                    </p>
                                    <p class="mt-1 text-xs text-amber-600">
                                        {{ member.unpaid_count }} unpaid period(s):
                                        {{ member.unpaid_periods?.slice(0, 5).join(', ') }}{{ member.unpaid_periods?.length > 5 ? '…' : '' }}
                                    </p>
                                </div>
                                <Link
                                    v-if="member.id"
                                    :href="route('monthly-contributions.index', { member: member.id })"
                                    class="shrink-0 rounded-md bg-rose-100 px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-200"
                                >
                                    View
                                </Link>
                            </li>
                        </ul>
                        <p
                            v-if="membersWithUnpaidContributions.length === 0"
                            class="py-8 text-center text-sm text-slate-400"
                        >
                            No members with unpaid contributions.
                        </p>
                    </div>
                </div>

                <!-- Top loaners / top agents -->
                <div
                    class="rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800">
                            <Trophy class="h-5 w-5 text-amber-500" />
                            Top Loaners
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Members with the most loans
                        </p>
                    </div>
                    <div class="max-h-80 overflow-auto p-4">
                        <ul class="space-y-3">
                            <li
                                v-for="(member, index) in topLoaners"
                                :key="member.id"
                                class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        :class="
                                            index === 0
                                                ? 'bg-amber-100 text-amber-700'
                                                : index === 1
                                                    ? 'bg-slate-200 text-slate-600'
                                                    : index === 2
                                                        ? 'bg-amber-200/80 text-amber-800'
                                                        : 'bg-slate-100 text-slate-600'
                                        "
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800">
                                            {{ member.name }}
                                        </p>
                                        <p class="text-sm text-slate-500">
                                            {{ member.email || '—' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-1">
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">
                                        {{ member.loans_count }} loan{{ member.loans_count !== 1 ? 's' : '' }}
                                    </span>
                                    <Link
                                        :href="route('loans.index', { member_id: member.id })"
                                        class="rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
                                        title="View loans"
                                    >
                                        <ArrowUpRight class="h-4 w-4" />
                                    </Link>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-if="topLoaners.length === 0"
                            class="py-8 text-center text-sm text-slate-400"
                        >
                            No loan data yet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
