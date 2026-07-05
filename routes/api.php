<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function (): void {
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'receive']);
});
