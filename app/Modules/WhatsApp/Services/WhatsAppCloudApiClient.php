<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Support\WhatsAppCredentials;
use App\Modules\WhatsApp\Support\WhatsAppSendResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class WhatsAppCloudApiClient
{
    /**
     * @param  list<string>  $bodyParameters
     * @param  list<string>|null  $bodyParameterNames  Meta named-template parameter names (same order as values).
     */
    public function sendTemplateMessage(
        WhatsAppCredentials $credentials,
        string $recipientPhone,
        string $templateName,
        string $languageCode,
        array $bodyParameters,
        ?array $bodyParameterNames = null,
    ): WhatsAppSendResult {
        $response = Http::timeout((int) config('whatsapp.timeout_seconds', 30))
            ->withToken($credentials->accessToken)
            ->acceptJson()
            ->post($this->messagesUrl($credentials->phoneNumberId), [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizeRecipient($recipientPhone),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode,
                    ],
                    'components' => $this->bodyComponents($bodyParameters, $bodyParameterNames),
                ],
            ]);

        return $this->parseSendResponse($response);
    }

    private function messagesUrl(string $phoneNumberId): string
    {
        $baseUrl = (string) config('whatsapp.graph_api_base_url');
        $version = (string) config('whatsapp.graph_api_version');

        return "{$baseUrl}/{$version}/{$phoneNumberId}/messages";
    }

    private function normalizeRecipient(string $phone): string
    {
        return ltrim(trim($phone), '+');
    }

    /**
     * @param  list<string>  $bodyParameters
     * @param  list<string>|null  $parameterNames
     * @return list<array<string, mixed>>
     */
    private function bodyComponents(array $bodyParameters, ?array $parameterNames = null): array
    {
        if ($bodyParameters === []) {
            return [];
        }

        return [
            [
                'type' => 'body',
                'parameters' => array_map(
                    function (string $text, int $index) use ($parameterNames): array {
                        $parameter = [
                            'type' => 'text',
                            'text' => $text,
                        ];

                        if ($parameterNames !== null) {
                            $parameter['parameter_name'] = $parameterNames[$index];
                        }

                        return $parameter;
                    },
                    $bodyParameters,
                    array_keys($bodyParameters),
                ),
            ],
        ];
    }

    private function parseSendResponse(Response $response): WhatsAppSendResult
    {
        $payload = $response->json();

        if (! $response->successful()) {
            $message = is_array($payload) && isset($payload['error']['message'])
                ? (string) $payload['error']['message']
                : 'WhatsApp Cloud API request failed.';

            throw new WhatsAppApiException(
                message: $message,
                statusCode: $response->status(),
                response: is_array($payload) ? $payload : null,
            );
        }

        $providerMessageId = is_array($payload)
            ? (string) ($payload['messages'][0]['id'] ?? '')
            : '';

        if ($providerMessageId === '') {
            throw new WhatsAppApiException(
                message: 'WhatsApp Cloud API response did not include a message ID.',
                statusCode: $response->status(),
                response: is_array($payload) ? $payload : null,
            );
        }

        return new WhatsAppSendResult(providerMessageId: $providerMessageId);
    }
}
