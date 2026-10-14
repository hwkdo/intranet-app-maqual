<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMaqual\Http\Controllers\QualDateiController;
use Hwkdo\IntranetAppMaqual\Livewire\Budgets;
use Hwkdo\IntranetAppMaqual\Livewire\MeineMitarbeiter;
use Hwkdo\IntranetAppMaqual\Livewire\MeineQuals;
use Hwkdo\IntranetAppMaqual\Livewire\MitarbeiterQuals;
use Hwkdo\IntranetAppMaqual\Livewire\Personalabteilung;
use Hwkdo\IntranetAppMaqual\Livewire\QualAnlegen;
use Hwkdo\IntranetAppMaqual\Livewire\QualShow;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'can:see-app-maqual'])->group(function (): void {
    Route::get('apps/maqual', MeineQuals::class)->name('apps.maqual.index');
    Route::get('apps/maqual/anlegen', QualAnlegen::class)->name('apps.maqual.anlegen');
    Route::get('apps/maqual/mitarbeiter', MeineMitarbeiter::class)->name('apps.maqual.mitarbeiter');
    Route::get('apps/maqual/mitarbeiter/{mitarbeiter}', MitarbeiterQuals::class)->name('apps.maqual.mitarbeiter.quals')->whereNumber('mitarbeiter');
    Route::get('apps/maqual/budgets', Budgets::class)->name('apps.maqual.budgets');
    Route::get('apps/maqual/quals/{qual}', QualShow::class)->name('apps.maqual.quals.show');
    Route::get('apps/maqual/quals/{qual}/dateien/{collection}', QualDateiController::class)->name('apps.maqual.quals.datei');

    Volt::route('apps/maqual/settings/notifications', 'apps.maqual.settings.notifications')->name('apps.maqual.settings.notifications');
    Volt::route('apps/maqual/info', 'apps.maqual.info')->name('apps.maqual.info');
    Volt::route('apps/maqual/manual', 'apps.maqual.manual')->name('apps.maqual.manual');
});

Route::middleware(['web', 'auth', 'can:manage-app-maqual'])->group(function (): void {
    Route::get('apps/maqual/personal', Personalabteilung::class)->name('apps.maqual.personal');
    Volt::route('apps/maqual/admin', 'apps.maqual.admin.index')->name('apps.maqual.admin.index');
});
