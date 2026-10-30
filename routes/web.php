<?php

use App\Http\Controllers\ConfigurationsController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionSettingController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root URL goes to the dashboard. Guests are sent to /login by the auth
// middleware, and after login Laravel returns them to /home (not back to the form).
Route::redirect('/', '/home');

Auth::routes(['register' => false]);

Route::middleware(['auth'])->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Members (wanachama)
    Route::get('members', [MemberController::class, 'index'])->name('members.index')->middleware('can:View Members');
    Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show')->middleware('can:View Members');
    Route::post('members', [MemberController::class, 'store'])->name('members.store')->middleware('can:Manage Members');
    Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update')->middleware('can:Manage Members');

    // Contribution settings (Masters)
    Route::get('masters/contribution-settings', [ContributionSettingController::class, 'index'])->name('contribution-settings.index')->middleware('can:View Contribution Settings');
    Route::post('masters/contribution-settings', [ContributionSettingController::class, 'store'])->name('contribution-settings.store')->middleware('can:Manage Contribution Settings');
    Route::put('masters/contribution-settings/{contributionSetting}', [ContributionSettingController::class, 'update'])->name('contribution-settings.update')->middleware('can:Manage Contribution Settings');
    Route::delete('masters/contribution-settings/{contributionSetting}', [ContributionSettingController::class, 'destroy'])->name('contribution-settings.destroy')->middleware('can:Manage Contribution Settings');

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
    ]);

    // reminder routes
    Route::resource('reminders', ReminderController::class);

    // Configurations Routes
    Route::get('/configurations', [ConfigurationsController::class, 'index'])->name('configurations.index')->middleware('can:View Settings');
    Route::post('/configurations', [ConfigurationsController::class, 'update'])->name('configurations.update')->middleware('can:View Settings');

    // Reports
    Route::get('reports/index', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/report', [ReportController::class, 'getReport'])->name('reports.getreport');

    /* expense routes */
    Route::get('expense-management/expenses', [ExpenseController::class, 'index'])->name('expense.index');
    Route::get('expense-management/record', [ExpenseController::class, 'create'])->name('expense.create');
    Route::post('expense-management/expenses', [ExpenseController::class, 'store'])->name('expense.store');
    Route::post('expense-management/expense-update', [ExpenseController::class, 'update'])->name('expense.update');
    Route::get('expense-management/expense-search', [ExpenseController::class, 'search'])->name('expense.search');

    // user roles
    Route::get('user-roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('user-roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('user-roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('user-roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::post('user-roles/update', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('user-roles/delete', [RoleController::class, 'destroy'])->name('roles.destroy');

    // users routes
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/register', [UserController::class, 'store'])->name('users.register');
    Route::post('users/update', [UserController::class, 'update'])->name('users.update');
    Route::put('users/delete', [UserController::class, 'delete'])->name('users.delete');
    Route::post('users/de-actiavate', [UserController::class, 'deActivate'])->name('users.deactivate');
    Route::post('users/change-password', [UserController::class, 'changePassword'])->name('change-password');
    Route::get('users/change-password', [UserController::class, 'changePasswordForm'])->name('change-pass-form');
    Route::post('user-profile/update', [UserController::class, 'updateProfile'])->name('update-profile');
    Route::get('users/search', [UserController::class, 'search'])->name('users.search');

});
