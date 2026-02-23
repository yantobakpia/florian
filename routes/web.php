<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderInvoiceController;

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

Route::get('/', function () {
    return view('welcome');
});

// Invoice route used by Filament action (named 'orders.invoice')
Route::get('orders/{order}/invoice', [OrderInvoiceController::class, 'show'])->name('orders.invoice');
