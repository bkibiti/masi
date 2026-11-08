<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConfigurationsController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionSettingController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OtherIncomeController;
use App\Http\Controllers\FineTypeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanTypeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberFineController;
use App\Http\Controllers\MemberLoginController;
use App\Http\Controllers\MyGuaranteeController;
use App\Http\Controllers\MyLoanController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\MyStatementController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsurePasswordIsNotExpired;
use App\Http\Middleware\EnsureUserIsLinkedToMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root URL goes to the dashboard. Guests are sent to /login by the auth
// middleware, and after login Laravel returns them to /home (not back to the form).
Route::redirect('/', '/home');

Auth::routes(['register' => false]);

Route::middleware(['auth', EnsurePasswordIsNotExpired::class])->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Forced password change (temporary passwords from member-login creation/reset)
    Route::get('account/force-password', [AccountController::class, 'forcePasswordForm'])->name('account.force-password');
    Route::post('account/force-password', [AccountController::class, 'forcePasswordUpdate'])->name('account.force-password.update');

    // My Account (spec section 3) - always scoped to auth()->user()->member
    Route::middleware(EnsureUserIsLinkedToMember::class)->prefix('account')->name('account.')->group(function () {
        Route::get('statement', [MyStatementController::class, 'index'])->name('statement');
        Route::get('statement/pdf', [MyStatementController::class, 'pdf'])->name('statement.pdf');

        Route::get('loans', [MyLoanController::class, 'index'])->name('loans.index');
        Route::get('loans/create', [MyLoanController::class, 'create'])->name('loans.create');
        Route::get('loans/preview', [MyLoanController::class, 'preview'])->name('loans.preview');
        Route::post('loans', [MyLoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}', [MyLoanController::class, 'show'])->name('loans.show');
        Route::post('loans/{loan}/cancel', [MyLoanController::class, 'cancel'])->name('loans.cancel');

        Route::get('guarantees', [MyGuaranteeController::class, 'index'])->name('guarantees.index');
        Route::post('guarantees/{guarantor}/accept', [MyGuaranteeController::class, 'accept'])->name('guarantees.accept');
        Route::post('guarantees/{guarantor}/decline', [MyGuaranteeController::class, 'decline'])->name('guarantees.decline');

        Route::get('profile', [MyProfileController::class, 'edit'])->name('profile');
        Route::put('profile', [MyProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/password', [MyProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // Members (wanachama)
    Route::get('members', [MemberController::class, 'index'])->name('members.index')->middleware('can:View Members');
    Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show')->middleware('can:View Members');
    Route::post('members', [MemberController::class, 'store'])->name('members.store')->middleware('can:Manage Members');
    Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update')->middleware('can:Manage Members');

    // Member login management (staff side of linking members and users)
    Route::get('members/login/bulk-create', [MemberLoginController::class, 'bulkCreateForm'])->name('members.login.bulk-create')->middleware('can:Manage Members');
    Route::post('members/login/bulk-create', [MemberLoginController::class, 'bulkCreateStore'])->name('members.login.bulk-create.store')->middleware('can:Manage Members');
    Route::post('members/{member}/login', [MemberLoginController::class, 'create'])->name('members.login.create')->middleware('can:Manage Members');
    Route::post('members/{member}/login/link', [MemberLoginController::class, 'link'])->name('members.login.link')->middleware('can:Manage Members');
    Route::post('members/{member}/login/reset-password', [MemberLoginController::class, 'resetPassword'])->name('members.login.reset-password')->middleware('can:Manage Members');
    Route::post('members/{member}/login/disable', [MemberLoginController::class, 'disable'])->name('members.login.disable')->middleware('can:Manage Members');
    Route::post('members/{member}/login/enable', [MemberLoginController::class, 'enable'])->name('members.login.enable')->middleware('can:Manage Members');

    // Contribution settings (Masters)
    Route::get('masters/contribution-settings', [ContributionSettingController::class, 'index'])->name('contribution-settings.index')->middleware('can:View Contribution Settings');
    Route::post('masters/contribution-settings', [ContributionSettingController::class, 'store'])->name('contribution-settings.store')->middleware('can:Manage Contribution Settings');
    Route::put('masters/contribution-settings/{contributionSetting}', [ContributionSettingController::class, 'update'])->name('contribution-settings.update')->middleware('can:Manage Contribution Settings');
    Route::delete('masters/contribution-settings/{contributionSetting}', [ContributionSettingController::class, 'destroy'])->name('contribution-settings.destroy')->middleware('can:Manage Contribution Settings');

    // Payment methods (Masters)
    Route::get('masters/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index')->middleware('can:View Payment Methods');
    Route::post('masters/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store')->middleware('can:Manage Payment Methods');
    Route::put('masters/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update')->middleware('can:Manage Payment Methods');
    Route::delete('masters/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy')->middleware('can:Manage Payment Methods');

    // Payment types (Masters)
    Route::get('masters/payment-types', [PaymentTypeController::class, 'index'])->name('payment-types.index')->middleware('can:View Payment Types');
    Route::post('masters/payment-types', [PaymentTypeController::class, 'store'])->name('payment-types.store')->middleware('can:Manage Payment Types');
    Route::put('masters/payment-types/{paymentType}', [PaymentTypeController::class, 'update'])->name('payment-types.update')->middleware('can:Manage Payment Types');
    Route::delete('masters/payment-types/{paymentType}', [PaymentTypeController::class, 'destroy'])->name('payment-types.destroy')->middleware('can:Manage Payment Types');

    // Fine types (Masters)
    Route::get('masters/fine-types', [FineTypeController::class, 'index'])->name('fine-types.index')->middleware('can:View Fine Types');
    Route::post('masters/fine-types', [FineTypeController::class, 'store'])->name('fine-types.store')->middleware('can:Manage Fine Types');
    Route::put('masters/fine-types/{fineType}', [FineTypeController::class, 'update'])->name('fine-types.update')->middleware('can:Manage Fine Types');
    Route::delete('masters/fine-types/{fineType}', [FineTypeController::class, 'destroy'])->name('fine-types.destroy')->middleware('can:Manage Fine Types');

    // Member fines (manual, lump-sum)
    Route::post('members/fines', [MemberFineController::class, 'store'])->name('member-fines.store')->middleware('can:Manage Fines');
    Route::put('members/fines/{memberFine}', [MemberFineController::class, 'update'])->name('member-fines.update')->middleware('can:Manage Fines');
    Route::delete('members/fines/{memberFine}', [MemberFineController::class, 'destroy'])->name('member-fines.destroy')->middleware('can:Manage Fines');

    // Loan types (Masters)
    Route::get('masters/loan-types', [LoanTypeController::class, 'index'])->name('loan-types.index')->middleware('can:View Loan Types');
    Route::post('masters/loan-types', [LoanTypeController::class, 'store'])->name('loan-types.store')->middleware('can:Manage Loan Types');
    Route::put('masters/loan-types/{loanType}', [LoanTypeController::class, 'update'])->name('loan-types.update')->middleware('can:Manage Loan Types');
    Route::delete('masters/loan-types/{loanType}', [LoanTypeController::class, 'destroy'])->name('loan-types.destroy')->middleware('can:Manage Loan Types');
    Route::get('masters/loan-types/preview', [LoanTypeController::class, 'preview'])->name('loan-types.preview')->middleware('can:View Loan Types');

    // Loans
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index')->middleware('can:View Loans');
    Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create')->middleware('can:Manage Loans');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store')->middleware('can:Manage Loans');
    Route::get('loans/preview', [LoanController::class, 'preview'])->name('loans.preview')->middleware('can:Manage Loans');
    Route::get('loans/{loan}', [LoanController::class, 'show'])->name('loans.show')->middleware('can:View Loans');
    Route::post('loans/{loan}/guarantors', [LoanController::class, 'storeGuarantor'])->name('loans.guarantors.store')->middleware('can:Manage Loans');
    Route::delete('loans/{loan}/guarantors/{guarantor}', [LoanController::class, 'destroyGuarantor'])->name('loans.guarantors.destroy')->middleware('can:Manage Loans');
    Route::post('loans/{loan}/guarantors/{guarantor}/respond', [LoanController::class, 'respondGuarantor'])->name('loans.guarantors.respond')->middleware('can:Manage Loans');
    Route::post('loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve')->middleware('can:Approve Loans');
    Route::post('loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject')->middleware('can:Approve Loans');
    Route::post('loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel')->middleware('can:Manage Loans');
    Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse')->middleware('can:Disburse Loans');
    Route::put('loans/{loan}/disbursement-date', [LoanController::class, 'updateDisbursementDate'])->name('loans.disbursement-date')->middleware('can:Disburse Loans');

    // Contributions: register + payments
    Route::get('contributions/register', [ContributionController::class, 'register'])->name('contributions.register')->middleware('can:View Contributions');

    Route::get('contributions/payments', [PaymentController::class, 'index'])->name('contributions.payments.index')->middleware('can:View Contributions');
    Route::get('contributions/payments/preview', [PaymentController::class, 'preview'])->name('contributions.payments.preview')->middleware('can:Record Payments');
    Route::post('contributions/payments', [PaymentController::class, 'store'])->name('contributions.payments.store')->middleware('can:Record Payments');
    Route::put('contributions/payments/{payment}', [PaymentController::class, 'update'])->name('contributions.payments.update')->middleware('can:Manage Payments');
    Route::delete('contributions/payments/{payment}', [PaymentController::class, 'destroy'])->name('contributions.payments.destroy')->middleware('can:Manage Payments');

    // Expense Categories routes
    Route::resource('masters/expense-categories', ExpenseCategoryController::class)->only([
        'index', 'store', 'update', 'destroy',
    ])->middleware('can:View Expense Categories');

    // reminder routes
    Route::resource('reminders', ReminderController::class)->middleware('can:View Reminders');

    // Configurations Routes
    Route::get('/configurations', [ConfigurationsController::class, 'index'])->name('configurations.index')->middleware('can:View Settings');
    Route::post('/configurations', [ConfigurationsController::class, 'update'])->name('configurations.update')->middleware('can:View Settings');

    // Reports
    Route::get('reports/index', [ReportController::class, 'index'])->name('reports.index')->middleware('can:View Reports');
    Route::get('reports/report', [ReportController::class, 'getReport'])->name('reports.getreport')->middleware('can:View Reports');

    /* expense routes */
    Route::get('expense-management/expenses', [ExpenseController::class, 'index'])->name('expense.index');
    Route::get('expense-management/record', [ExpenseController::class, 'create'])->name('expense.create');
    Route::post('expense-management/expenses', [ExpenseController::class, 'store'])->name('expense.store');
    Route::post('expense-management/expense-update', [ExpenseController::class, 'update'])->name('expense.update');
    Route::get('expense-management/expense-search', [ExpenseController::class, 'search'])->name('expense.search');

    /* other income (group income not belonging to a member) */
    Route::get('other-income', [OtherIncomeController::class, 'index'])->name('other-income.index')->middleware('can:View Other Income');
    Route::post('other-income', [OtherIncomeController::class, 'store'])->name('other-income.store')->middleware('can:Manage Other Income');
    Route::put('other-income/{otherIncome}', [OtherIncomeController::class, 'update'])->name('other-income.update')->middleware('can:Manage Other Income');
    Route::delete('other-income/{otherIncome}', [OtherIncomeController::class, 'destroy'])->name('other-income.destroy')->middleware('can:Manage Other Income');

    // user roles
    Route::get('user-roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:View Roles');
    Route::get('user-roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('can:Manage Roles');
    Route::post('user-roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:Manage Roles');
    Route::get('user-roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('can:Manage Roles');
    Route::post('user-roles/update', [RoleController::class, 'update'])->name('roles.update')->middleware('can:Manage Roles');
    Route::delete('user-roles/delete', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:Manage Roles');

    // users routes
    Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('can:View Users');
    Route::post('users/register', [UserController::class, 'store'])->name('users.register')->middleware('can:Manage Users');
    Route::post('users/update', [UserController::class, 'update'])->name('users.update')->middleware('can:Manage Users');
    Route::put('users/delete', [UserController::class, 'delete'])->name('users.delete')->middleware('can:Manage Users');
    Route::post('users/de-actiavate', [UserController::class, 'deActivate'])->name('users.deactivate')->middleware('can:Manage Users');
    // Self-service (any authenticated user, their own password/profile only): no permission gate.
    Route::post('users/change-password', [UserController::class, 'changePassword'])->name('change-password');
    Route::get('users/change-password', [UserController::class, 'changePasswordForm'])->name('change-pass-form');
    Route::post('user-profile/update', [UserController::class, 'updateProfile'])->name('update-profile');
    Route::get('users/search', [UserController::class, 'search'])->name('users.search')->middleware('can:View Users');

});
