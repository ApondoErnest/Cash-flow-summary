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

    /*
    |--------------------------------------------------------------------------
    | Import notifications
    |--------------------------------------------------------------------------
    |
    | Meta template import_activity_summary (Utility) — seven body parameters:
    | center_name, import_period, inspection_count, category_summary,
    | amount_ht, amount_vat, amount_ttc. See docs/api/README.md.
    |
    */
    'import_template' => env('WHATSAPP_IMPORT_TEMPLATE', 'import_activity_summary'),

    'import_template_language' => env('WHATSAPP_IMPORT_TEMPLATE_LANGUAGE', 'en'),

    'default_summary_time' => env('WHATSAPP_DEFAULT_SUMMARY_TIME', '18:00'),

    /** @var list<string> Body parameter names for import_activity_summary (Meta named format). */
    'import_template_body_parameter_names' => [
        'center_name',
        'import_period',
        'inspection_count',
        'category_summary',
        'amount_ht',
        'amount_vat',
        'amount_ttc',
    ],

    'timeout_seconds' => (int) env('WHATSAPP_API_TIMEOUT', 30),

    'max_attempts' => (int) env('WHATSAPP_QUEUE_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Settings test message
    |--------------------------------------------------------------------------
    |
    | Meta’s default hello_world template is available on test numbers without
    | custom template approval. Override if your Business Manager uses another.
    |
    */
    'test_template' => env('WHATSAPP_TEST_TEMPLATE', 'hello_world'),

    'test_template_language' => env('WHATSAPP_TEST_TEMPLATE_LANGUAGE', 'en_US'),

    /** @var list<int> Seconds between queue retries (exponential backoff base). */
    'retry_backoff_seconds' => [60, 300, 900],

    /*
    | Meta App Secret — used to validate X-Hub-Signature-256 on inbound webhooks.
    | Required when any organization has a webhook verify token configured.
    */
    'app_secret' => env('WHATSAPP_APP_SECRET'),

];
