<?php

use App\Http\Controllers\ConfigurationsController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MemberController;
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
