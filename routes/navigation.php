<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/imports/create', 'pages.placeholder', ['title' => 'Import CSV'])->name('imports.create');
Route::view('/imports', 'pages.placeholder', ['title' => 'Imports'])->name('imports.index');
Route::view('/records', 'pages.placeholder', ['title' => 'Records'])->name('records.index');
Route::view('/daily-versions', 'pages.placeholder', ['title' => 'Daily Versions'])->name('daily-versions.index');
Route::view('/revisions', 'pages.placeholder', ['title' => 'Revisions'])->name('revisions.index');
Route::view('/reports', 'pages.placeholder', ['title' => 'Reports'])->name('reports.index');
Route::view('/anomalies', 'pages.placeholder', ['title' => 'Anomalies'])->name('anomalies.index');
Route::view('/whatsapp-history', 'pages.placeholder', ['title' => 'WhatsApp History'])->name('whatsapp-history.index');

Route::view('/centers', 'pages.placeholder', ['title' => 'Manage Centers'])->name('centers.index');
Route::view('/users', 'pages.placeholder', ['title' => 'Manage Users'])->name('users.index');
Route::view('/settings/organization', 'pages.placeholder', ['title' => 'Organization Settings'])->name('settings.organization');
Route::view('/settings/whatsapp', 'pages.placeholder', ['title' => 'WhatsApp Settings'])->name('settings.whatsapp');
Route::view('/security', 'pages.placeholder', ['title' => 'Security'])->name('security.index');
Route::view('/audit-logs', 'pages.placeholder', ['title' => 'Audit Logs'])->name('audit-logs.index');
