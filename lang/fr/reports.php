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
        'title' => 'Exporter le rapport',
        'description' => 'Générez un fichier pour la période sélectionnée. Les téléchargements restent disponibles jusqu\'à expiration.',
        'recent_title' => 'Exports récents',
        'queued' => 'Export :format mis en file d\'attente — votre fichier sera prêt sous peu.',
        'invalid_period' => 'Choisissez une plage de dates personnalisée valide avant d\'exporter.',
        'formats' => [
            'csv' => 'CSV',
            'xlsx' => 'Excel',
            'pdf' => 'PDF',
        ],
        'statuses' => [
            'pending' => 'En file d\'attente',
            'processing' => 'Génération',
            'completed' => 'Prêt',
            'failed' => 'Échec',
            'expired' => 'Expiré',
        ],
        'columns' => [
            'requested_at' => 'Demandé le',
            'format' => 'Format',
            'period' => 'Période',
            'status' => 'Statut',
            'expires_at' => 'Expire le',
            'actions' => 'Actions',
        ],
        'actions' => [
            'download' => 'Télécharger',
        ],
        'file' => [
            'title' => 'Rapport du centre',
            'center' => 'Centre',
            'period' => 'Période',
            'totals' => 'Totaux',
            'generated_at' => 'Généré le :datetime',
        ],
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Centre',
            'subtitle' => 'Consultez les totaux journaliers et les jours de soumission manquants pour :center.',
        ],
    ],
];
