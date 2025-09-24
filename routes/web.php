<?php

use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->group(function () {

    Route::get('/', function () {
        $user = auth()->user();

        if ($user->hasRole('vendeur')) {
            return to_route('shops.show', $user->shop_id);
        }else{
            return to_route('shops');
        }
    })->name('home');

    Volt::route('dashboard', 'dashboard.index')
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');

    // Management
    Volt::route('/users', 'user.index')->name('users');
    Volt::route('/roles', 'role.index')->name('roles');

    Volt::route('/shops', 'shop.index')->name('shops');
    Volt::route('/shops/{shop}', 'shop.show')->name('shops.show');
    Volt::route('/products', 'product.index')->name('products');
    Volt::route('/sales', 'sale.index')->name('sales');

// Receipts
    Route::get('/receipts/{sale}/pdf', [ReceiptController::class, 'generate'])->name('receipts.pdf');
    Route::get('/receipts/{sale}/print', [ReceiptController::class, 'print'])->name('receipts.print');
    Route::get('/receipts/{sale}/download', [ReceiptController::class, 'download'])->name('receipts.download');
    Route::get('/receipts/{sale}/auto-print', [ReceiptController::class, 'autoPrint'])->name('receipts.auto-print');

// Reports (CSV-compatible with Excel)
    Route::get('/shops/{shop}/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/shops/{shop}/reports/hairdressers', [ReportController::class, 'hairdressers'])->name('reports.hairdressers');
    Route::get('/shops/{shop}/reports/products', [ReportController::class, 'products'])->name('reports.products');
});

require __DIR__.'/auth.php';
