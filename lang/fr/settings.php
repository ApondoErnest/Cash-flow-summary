<?php

return [
    'shell' => [
        'notice' => 'Cet espace de paramètres est un aperçu provisoire. Les valeurs affichées sont en lecture seule jusqu\'à l\'activation de l\'enregistrement des paramètres d\'organisation.',
    ],

    'common' => [
        'not_set' => 'Non renseigné',
        'save_changes' => 'Enregistrer',
    ],

    'organization' => [
        'title' => 'Paramètres organisation',
        'description' => 'Consultez le profil de votre organisation, les valeurs régionales par défaut et les coordonnées.',
        'profile_title' => 'Profil organisation',
        'stats' => [
            'currency' => 'Devise',
            'timezone' => 'Fuseau horaire',
            'language' => 'Langue par défaut',
            'status' => 'Statut',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'fields' => [
            'name' => 'Nom de l\'organisation',
            'code' => 'Code organisation',
            'default_language' => 'Langue par défaut',
            'currency' => 'Devise',
            'timezone' => 'Fuseau horaire',
            'contact_email' => 'E-mail de contact',
            'contact_phone' => 'Téléphone de contact',
        ],
    ],

    'whatsapp' => [
        'title' => 'Paramètres WhatsApp',
        'description' => 'Configurez le numéro de notification du propriétaire et les identifiants Meta WhatsApp Cloud API.',
        'configured_notice' => 'WhatsApp est configuré. Les notifications sortantes utiliseront ces identifiants lorsque la messagerie sera activée.',
        'incomplete_notice' => 'Complétez tous les champs ci-dessous pour activer les notifications WhatsApp pour cette organisation.',
        'saved' => 'Paramètres WhatsApp enregistrés.',
        'saving' => 'Enregistrement…',
        'notifications_title' => 'Configuration',
        'fields' => [
            'owner_phone' => 'Numéro WhatsApp du propriétaire',
            'owner_phone_help' => 'Reçoit les alertes opérationnelles et les résumés quotidiens. Format E.164 avec indicatif pays.',
            'phone_number_id' => 'ID du numéro de téléphone',
            'access_token' => 'Jeton d\'accès',
            'access_token_help' => 'Jeton permanent depuis Meta Business Manager. Stocké chiffré.',
            'access_token_configured_help' => 'Un jeton est enregistré. Laissez vide pour le conserver ou saisissez-en un nouveau pour le remplacer.',
            'webhook_verify_token' => 'Jeton de vérification webhook',
            'webhook_verify_token_help' => 'Secret partagé pour la vérification webhook Meta. Stocké chiffré.',
            'webhook_verify_token_configured_help' => 'Un jeton est enregistré. Laissez vide pour le conserver ou saisissez-en un nouveau pour le remplacer.',
        ],
        'placeholders' => [
            'owner_phone' => '+237612345678',
            'phone_number_id' => 'ID Meta du numéro',
            'access_token' => 'Coller le jeton d\'accès',
            'access_token_configured' => 'Laisser vide pour conserver le jeton actuel',
            'webhook_verify_token' => 'Jeton de vérification webhook',
            'webhook_verify_token_configured' => 'Laisser vide pour conserver le jeton actuel',
        ],
    ],

    'security' => [
        'title' => 'Sécurité',
        'description' => 'Consultez les politiques d\'authentification et gérez l\'authentification à deux facteurs du propriétaire.',
        'password_policy_title' => 'Politique de mot de passe',
        'password_policy_description' => 'Appliquée lors de la création d\'utilisateurs ou du changement de mot de passe.',
        'password_rules' => [
            'min_length' => 'Minimum :count caractères',
            'mixed_case' => 'Lettres majuscules et minuscules',
            'numbers' => 'Au moins un chiffre',
            'symbols' => 'Au moins un symbole',
        ],
        'session_title' => 'Expiration de session inactive',
        'session_description' => 'Les utilisateurs sont déconnectés après cette période d\'inactivité.',
        'session_minutes' => 'minutes',
        'two_factor_title' => 'Authentification à deux facteurs (propriétaire)',
        'two_factor_description' => 'Les propriétaires doivent activer une application d\'authentification avant d\'accéder aux fonctionnalités opérationnelles.',
        'two_factor_enabled' => 'Authentification à deux facteurs activée',
        'two_factor_disabled' => 'Authentification à deux facteurs non activée',
        'setup_two_factor' => 'Configurer l\'A2F',
        'manage_two_factor' => 'Gérer l\'A2F',
    ],
];
