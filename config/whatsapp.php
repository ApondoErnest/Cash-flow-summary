<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Credentials (phone number ID, access token, owner phone) live in
    | organization_settings — see docs/api/README.md.
    |
    */

    'graph_api_version' => env('WHATSAPP_GRAPH_API_VERSION', 'v21.0'),

    'graph_api_base_url' => rtrim((string) env('WHATSAPP_GRAPH_API_BASE_URL', 'https://graph.facebook.com'), '/'),

    'default_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),

    'timeout_seconds' => (int) env('WHATSAPP_API_TIMEOUT', 30),

    'max_attempts' => (int) env('WHATSAPP_QUEUE_MAX_ATTEMPTS', 3),

    /** @var list<int> Seconds between queue retries (exponential backoff base). */
    'retry_backoff_seconds' => [60, 300, 900],

    /*
    | Meta App Secret — used to validate X-Hub-Signature-256 on inbound webhooks.
    | Required when any organization has a webhook verify token configured.
    */
    'app_secret' => env('WHATSAPP_APP_SECRET'),

];
