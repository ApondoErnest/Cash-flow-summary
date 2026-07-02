<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/imports/create', \App\Modules\CsvVerification\Livewire\ImportCsv::class)->name('imports.create');
Route::get('/imports/{import}/result', \App\Modules\CsvImports\Livewire\ImportResultPage::class)->name('imports.result');
Route::get('/imports/{import}', \App\Modules\CsvImports\Livewire\ImportDetail::class)->name('imports.show');
Route::get('/imports', \App\Modules\CsvImports\Livewire\ImportList::class)->name('imports.index');
Route::get('/records', \App\Modules\CsvImports\Livewire\RecordsExplorer::class)->name('records.index');
Route::get('/daily-versions', \App\Modules\DailyVersions\Livewire\DailyVersionList::class)->name('daily-versions.index');
Route::get('/revisions', \App\Modules\DailyVersions\Livewire\RevisionApproval::class)->name('revisions.index');
Route::get('/reports', \App\Modules\Reports\Livewire\CenterReport::class)->name('reports.index');
Route::get('/anomalies', \App\Modules\Reports\Livewire\AnomalyList::class)->name('anomalies.index');
Route::get('/whatsapp-history', \App\Modules\WhatsApp\Livewire\WhatsappHistoryPage::class)->name('whatsapp-history.index');
