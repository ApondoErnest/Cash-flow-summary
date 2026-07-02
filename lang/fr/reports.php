<?php

return [
    'page_title' => 'Rapports',
    'title' => 'Rapports du centre',
    'description' => 'Totaux journaliers issus des instantanés actifs pour le centre et la période sélectionnés.',
    'center_label' => 'Rapports affichés pour',
    'empty' => 'Aucune donnée pour cette période — importez des fichiers CSV pour générer les résumés journaliers.',
    'stats' => [
        'total_ttc' => 'Total TTC',
        'total_ht' => 'Montant HT',
        'total_vat' => 'Montant TVA',
        'record_count' => 'Enregistrements',
        'days_with_data' => '{0} Aucun jour avec données|{1} :count jour avec données|[2,*] :count jours avec données',
    ],
    'table' => [
        'title' => 'Détail journalier',
        'description' => 'Totaux des instantanés actifs pour :period.',
    ],
    'columns' => [
        'business_date' => 'Date métier',
        'record_count' => 'Enregistrements',
        'ht' => 'Montant HT',
        'vat' => 'Montant TVA',
        'ttc' => 'Total TTC',
    ],
    'missing_submissions' => [
        'title' => '{1} :count jour de soumission manquant|[2,*] :count jours de soumission manquants',
        'description' => 'Jours ouvrés attendus dans :period sans instantané journalier actif.',
        'and_more' => '…et :count de plus',
    ],
    'export' => [
        'coming_soon_title' => 'Exports à venir',
        'coming_soon_description' => 'Les exports CSV, Excel et PDF seront disponibles prochainement.',
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Centre',
            'subtitle' => 'Consultez les totaux journaliers et les jours de soumission manquants pour :center.',
        ],
    ],
];
