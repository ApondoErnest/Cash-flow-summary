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
        'deployment_notice' => 'Les identifiants WhatsApp initiaux peuvent être configurés au déploiement (BR-018). L\'enregistrement depuis cet écran sera activé lorsque le stockage des paramètres d\'organisation sera disponible.',
        'notifications_title' => 'Notifications propriétaire',
        'api_title' => 'Meta Cloud API',
        'fields' => [
            'owner_phone' => 'Numéro WhatsApp du propriétaire',
            'owner_phone_help' => 'Reçoit les alertes opérationnelles et les résumés quotidiens. Inclure l\'indicatif pays.',
            'phone_number_id' => 'ID du numéro de téléphone',
            'access_token' => 'Jeton d\'accès',
            'access_token_help' => 'Stocké chiffré lorsque l\'enregistrement des paramètres sera activé.',
            'webhook_verify_token' => 'Jeton de vérification webhook',
        ],
        'placeholders' => [
            'owner_phone' => '+237 6XX XXX XXX',
            'phone_number_id' => 'ID Meta du numéro',
            'access_token' => '••••••••••••••••',
            'webhook_verify_token' => 'Jeton de vérification webhook',
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
