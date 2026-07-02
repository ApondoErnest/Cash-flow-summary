<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/imports/create', 'pages.placeholder', ['pageKey' => 'imports.create'])->name('imports.create');
Route::view('/imports', 'pages.placeholder', ['pageKey' => 'imports.index'])->name('imports.index');
Route::view('/records', 'pages.placeholder', ['pageKey' => 'records.index'])->name('records.index');
Route::view('/daily-versions', 'pages.placeholder', ['pageKey' => 'daily-versions.index'])->name('daily-versions.index');
Route::view('/revisions', 'pages.placeholder', ['pageKey' => 'revisions.index'])->name('revisions.index');
Route::view('/reports', 'pages.placeholder', ['pageKey' => 'reports.index'])->name('reports.index');
Route::view('/anomalies', 'pages.placeholder', ['pageKey' => 'anomalies.index'])->name('anomalies.index');
Route::view('/whatsapp-history', 'pages.placeholder', ['pageKey' => 'whatsapp-history.index'])->name('whatsapp-history.index');

Route::view('/centers', 'pages.placeholder', ['pageKey' => 'centers.index'])->name('centers.index');
Route::view('/users', 'pages.placeholder', ['pageKey' => 'users.index'])->name('users.index');
Route::view('/settings/organization', 'pages.placeholder', ['pageKey' => 'settings.organization'])->name('settings.organization');
Route::view('/settings/whatsapp', 'pages.placeholder', ['pageKey' => 'settings.whatsapp'])->name('settings.whatsapp');
Route::view('/security', 'pages.placeholder', ['pageKey' => 'security.index'])->name('security.index');
Route::view('/audit-logs', 'pages.placeholder', ['pageKey' => 'audit-logs.index'])->name('audit-logs.index');
