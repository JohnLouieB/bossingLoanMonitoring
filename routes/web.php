<?php

use App\Http\Controllers\CapitalCashFlowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MonthlyContributionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Profile routes - viewers can view but only admins can update
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::middleware('admin')->group(function () {
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // Members routes - viewers can view, admins can manage
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
    Route::middleware('admin')->group(function () {
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });

    // Loans routes - viewers can view, admins can manage
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}/details', [LoanController::class, 'show'])->name('loans.show');
    Route::middleware('admin')->group(function () {
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::patch('/loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
        Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
        Route::patch('/loans/{loan}/monthly-interest', [LoanController::class, 'updateMonthlyInterest'])->name('loans.update-monthly-interest');
        Route::post('/loans/{loan}/advance-payment', [LoanController::class, 'storeAdvancePayment'])->name('loans.store-advance-payment');
        Route::delete('/loans/{loan}/advance-payment/{advancePayment}', [LoanController::class, 'revertAdvancePayment'])->name('loans.revert-advance-payment');
    });

    // Monthly Contributions routes - viewers can view, admins can manage
    Route::get('/monthly-contributions', [MonthlyContributionController::class, 'index'])->name('monthly-contributions.index');
    Route::middleware('admin')->group(function () {
        Route::patch('/monthly-contributions/update-all-amounts', [MonthlyContributionController::class, 'updateAllAmounts'])->name('monthly-contributions.update-all-amounts');
        Route::patch('/monthly-contributions/{member}/status', [MonthlyContributionController::class, 'updateStatus'])->name('monthly-contributions.update-status');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    // Capital and Cash Flow routes - viewers can view, admins can manage
    Route::get('/capital-cash-flow', [CapitalCashFlowController::class, 'index'])->name('capital-cash-flow.index');
    Route::middleware('admin')->group(function () {
        Route::patch('/capital-cash-flow', [CapitalCashFlowController::class, 'update'])->name('capital-cash-flow.update');
        Route::post('/capital-cash-flow/deductions', [CapitalCashFlowController::class, 'storeDeduction'])->name('capital-cash-flow.deductions.store');
    });
});

require __DIR__.'/auth.php';
