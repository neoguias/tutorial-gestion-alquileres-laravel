<?php

use App\Models\Receipt;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inmuebles');

Route::livewire('/inmuebles', 'pages::properties')->name('properties');
Route::livewire('/inmuebles/{property}', 'pages::property')->name('property');
Route::livewire('/clientes', 'pages::contacts')->name('contacts');
Route::livewire('/visitas', 'pages::visits')->name('visits');
Route::livewire('/alquileres', 'pages::leases')->name('leases');
Route::livewire('/alquileres/{lease}', 'pages::lease')->name('lease');

// El recibo listo para imprimir. Solo existe cuando se ha cobrado.
Route::get('/recibos/{receipt}', function (Receipt $receipt) {
    abort_unless($receipt->paid_on, 404);

    return view('receipt', ['receipt' => $receipt->load('lease.property', 'lease.tenant')]);
})->name('receipt');
