<?php

use App\Livewire\Actions\Logout;
use App\Support\Permisos;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route('pedidos.index'));

Route::post('/logout', function (Logout $logout) {
    $logout();

    return redirect('/login');
})->middleware('auth')->name('logout');

// Alias que Breeze usa internamente tras el login.
Route::get('/dashboard', fn () => redirect()->route('pedidos.index'))
    ->middleware(['auth'])->name('dashboard');

Route::view('profile', 'profile')->middleware(['auth', 'activo'])->name('profile');

Route::middleware(['auth', 'activo'])->group(function () {
    // Seguimiento de pedidos por liberar
    Volt::route('/pedidos-liberar', 'pedidos.index')
        ->middleware('can:'.Permisos::PEDIDOS_VER)->name('pedidos.index');
    Volt::route('/pedidos-liberar/{pedido}', 'pedidos.show')
        ->middleware('can:'.Permisos::PEDIDOS_VER)->name('pedidos.show');

    Volt::route('/cargues/correo', 'cargues.correo')
        ->middleware('can:'.Permisos::CORREO_GENERAR)->name('cargues.correo');

    Volt::route('/inventario', 'inventario.index')
        ->middleware('can:'.Permisos::SEGUIMIENTO_EDITAR)->name('inventario.index');
    Volt::route('/inventario/detalle', 'inventario.detalle')
        ->middleware('can:'.Permisos::SEGUIMIENTO_EDITAR)->name('inventario.detalle');

    // Administración
    Route::prefix('admin')->name('admin.')->group(function () {
        Volt::route('/usuarios', 'admin.usuarios.index')
            ->middleware('can:'.Permisos::ADMIN_USUARIOS)->name('usuarios.index');
        Volt::route('/roles', 'admin.roles.index')
            ->middleware('can:'.Permisos::ADMIN_ROLES)->name('roles.index');
        Volt::route('/permisos', 'admin.permisos.index')
            ->middleware('can:'.Permisos::ADMIN_ROLES)->name('permisos.index');
    });
});

require __DIR__.'/auth.php';
