<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ApprovalController;

// Auth Routes
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::any('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', [GroupController::class, 'index'])->name('dashboard');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    
    Route::prefix('groups/{group}')->group(function () {
        Route::get('/', [GroupController::class, 'show'])->name('groups.show');
        
        // Members
        Route::post('/members', [GroupController::class, 'addMember'])->name('groups.members.add');
        Route::post('/members/{user}/remove', [GroupController::class, 'removeMember'])->name('groups.members.remove');
        
        // Expenses
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        
        // Settlements
        Route::get('/settlements/create', [SettlementController::class, 'create'])->name('settlements.create');
        Route::post('/settlements', [SettlementController::class, 'store'])->name('settlements.store');
        
        // Imports
        Route::get('/imports', [ImportController::class, 'showUpload'])->name('groups.imports');
        Route::post('/imports', [ImportController::class, 'import'])->name('groups.imports.store');
        Route::get('/imports/{importLog}/report', [ImportController::class, 'showReport'])->name('groups.imports.report');
        
        // Approvals
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('groups.approvals');
        Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('groups.approvals.approve');
        Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])->name('groups.approvals.reject');
    });
});
