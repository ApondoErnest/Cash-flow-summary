<?php

return [
    'page_title' => 'Enregistrements',
    'title' => 'Enregistrements de trésorerie',
    'description' => 'Parcourir les enregistrements du grand livre pour le centre actif.',
    'center_label' => 'Enregistrements pour',
    'table_title' => 'Grand livre',
    'table_description' => 'Rechercher par client ou plaque, filtrer par statut ou date d\'enregistrement.',
    'detail_title' => 'Détail de l\'enregistrement',
    'close_detail' => 'Fermer le détail',
    'search_placeholder' => 'Rechercher client ou plaque…',
    'empty' => 'Aucun enregistrement — importez un CSV pour alimenter le grand livre.',
    'view_detail' => 'Voir',
    'view_first_import' => 'Voir le premier import : :filename',
    'filters' => [
        'all_completion' => 'Tous les statuts de complétion',
        'all_financial' => 'Tous les statuts financiers',
        'from' => 'Du',
        'to' => 'Au',
    ],
    'columns' => [
        'registration_date' => 'Date d\'enregistrement',
        'completion_date' => 'Date de complétion',
        'customer' => 'Client',
        'licence_plate' => 'Plaque',
        'category' => 'Catégorie',
        'inspection_type' => 'Inspection',
        'ht' => 'Montant HT',
        'vat' => 'Montant TVA',
        'ttc' => 'Montant TTC',
        'completion_status' => 'Complétion',
        'financial_status' => 'Statut financier',
        'first_seen_at' => 'Première apparition',
        'actions' => 'Actions',
    ],
    'status' => [
        'completion' => [
            'completed' => 'Terminé',
            'unfinished' => 'Non terminé',
        ],
        'financial' => [
            'revenue' => 'Générateur de revenus',
            'zero_value' => 'Valeur nulle',
        ],
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Centre',
            'subtitle' => 'Rechercher les enregistrements du grand livre pour :center.',
        ],
    ],
];
