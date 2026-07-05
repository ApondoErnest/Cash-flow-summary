<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Services\WhatsAppWebhookService;
use App\Modules\WhatsApp\Support\WhatsAppWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppWebhookService $webhookService): Response|SymfonyResponse
    {
        if (! $webhookService->webhooksEnabled()) {
            abort(404);
        }

        $challenge = $webhookService->verifySubscription(
            mode: (string) $request->query('hub_mode', ''),
            verifyToken: (string) $request->query('hub_verify_token', ''),
            challenge: (string) $request->query('hub_challenge', ''),
        );

        if ($challenge === null) {
            abort(403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function receive(
        Request $request,
        WhatsAppWebhookService $webhookService,
        WhatsAppWebhookSignatureVerifier $signatureVerifier,
    ): Response {
        if (! $webhookService->webhooksEnabled()) {
            abort(404);
        }

        $rawBody = $request->getContent();

        if (! $signatureVerifier->isValid($rawBody, $request->header('X-Hub-Signature-256'))) {
            abort(403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $webhookService->processPayload($payload);

        return response('', 200);
    }
}
