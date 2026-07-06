<?php

declare(strict_types=1);

use App\Modules\CsvImports\Http\Controllers\ImportErrorDownloadController;
use App\Modules\CsvImports\Livewire\ImportDetail;
use App\Modules\CsvImports\Livewire\ImportList;
use App\Modules\CsvImports\Livewire\ImportResultPage;
use App\Modules\CsvImports\Livewire\RecordsExplorer;
use App\Modules\CsvVerification\Http\Controllers\VerificationErrorDownloadController;
use App\Modules\CsvVerification\Livewire\ImportCsv;
use App\Modules\DailyVersions\Livewire\DailyVersionList;
use App\Modules\DailyVersions\Livewire\RevisionApproval;
use App\Modules\Reports\Http\Controllers\ExportDownloadController;
use App\Modules\Reports\Livewire\AnomalyList;
use App\Modules\Reports\Livewire\CenterReport;
use App\Modules\WhatsApp\Livewire\WhatsappHistoryPage;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')->group(function (): void {
    Route::get('/imports/{import}/errors/download', ImportErrorDownloadController::class)->name('imports.errors.download');
    Route::get('/verifications/{token}/errors/download', VerificationErrorDownloadController::class)->name('verifications.errors.download');
    Route::get('/exports/{exportRequest}/download', ExportDownloadController::class)->name('exports.download');
});

Route::get('/imports/create', ImportCsv::class)->name('imports.create');
Route::get('/imports/{import}/result', ImportResultPage::class)->name('imports.result');
Route::get('/imports/{import}', ImportDetail::class)->name('imports.show');
Route::get('/imports', ImportList::class)->name('imports.index');
Route::get('/records', RecordsExplorer::class)->name('records.index');
Route::get('/daily-versions', DailyVersionList::class)->name('daily-versions.index');
Route::get('/revisions', RevisionApproval::class)->name('revisions.index');
Route::get('/reports', CenterReport::class)->name('reports.index');
Route::get('/anomalies', AnomalyList::class)->name('anomalies.index');
Route::get('/whatsapp-history', WhatsappHistoryPage::class)->name('whatsapp-history.index');
