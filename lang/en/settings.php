<?php

return [
    'shell' => [
        'notice' => 'This settings area is a preview shell. Values shown here are read-only until organization settings persistence is enabled in a later step.',
    ],

    'common' => [
        'not_set' => 'Not set',
        'save_changes' => 'Save changes',
    ],

    'organization' => [
        'title' => 'Organization Settings',
        'description' => 'Review your organization profile, regional defaults, and contact information.',
        'profile_title' => 'Organization profile',
        'stats' => [
            'currency' => 'Currency',
            'timezone' => 'Timezone',
            'language' => 'Default language',
            'status' => 'Status',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'fields' => [
            'name' => 'Organization name',
            'code' => 'Organization code',
            'default_language' => 'Default language',
            'currency' => 'Currency',
            'timezone' => 'Timezone',
            'contact_email' => 'Contact email',
            'contact_phone' => 'Contact phone',
        ],
    ],

    'whatsapp' => [
        'title' => 'WhatsApp Settings',
        'description' => 'Configure the owner notification number and Meta WhatsApp Cloud API credentials.',
        'deployment_notice' => 'Initial WhatsApp credentials may be configured at deployment (BR-018). Saving from this screen will be enabled when organization settings storage is wired.',
        'notifications_title' => 'Owner notifications',
        'api_title' => 'Meta Cloud API',
        'fields' => [
            'owner_phone' => 'Owner WhatsApp number',
            'owner_phone_help' => 'Receives operational alerts and daily summaries. Must include country code.',
            'phone_number_id' => 'Phone number ID',
            'access_token' => 'Access token',
            'access_token_help' => 'Stored encrypted when settings persistence is enabled.',
            'webhook_verify_token' => 'Webhook verify token',
        ],
        'placeholders' => [
            'owner_phone' => '+237 6XX XXX XXX',
            'phone_number_id' => 'Meta phone number ID',
            'access_token' => '••••••••••••••••',
            'webhook_verify_token' => 'Webhook verification token',
        ],
    ],

    'security' => [
        'title' => 'Security Settings',
        'description' => 'Review authentication policies and manage owner two-factor authentication.',
        'password_policy_title' => 'Password policy',
        'password_policy_description' => 'Applied when creating users or changing passwords.',
        'password_rules' => [
            'min_length' => 'Minimum :count characters',
            'mixed_case' => 'Upper and lower case letters',
            'numbers' => 'At least one number',
            'symbols' => 'At least one symbol',
        ],
        'session_title' => 'Session idle timeout',
        'session_description' => 'Users are signed out after this period of inactivity.',
        'session_minutes' => 'minutes',
        'two_factor_title' => 'Owner two-factor authentication',
        'two_factor_description' => 'Owners must enable an authenticator app before accessing operational features.',
        'two_factor_enabled' => 'Two-factor authentication is enabled',
        'two_factor_disabled' => 'Two-factor authentication is not enabled',
        'setup_two_factor' => 'Set up two-factor',
        'manage_two_factor' => 'Manage two-factor',
    ],
];
