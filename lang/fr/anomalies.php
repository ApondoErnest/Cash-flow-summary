<?php

return [
    'page_title' => 'Anomalies',
    'title' => 'Anomalies',
    'description' => 'Consultez les problèmes de qualité des données et de rapprochement pour le centre actif.',
    'center_label' => 'Anomalies pour',
    'table_title' => 'Journal des anomalies',
    'table_description' => 'Filtrer par type, statut de résolution ou date de détection.',
    'detail_title' => 'Détail de l\'anomalie',
    'close_detail' => 'Fermer le détail',
    'empty' => 'Aucune anomalie enregistrée pour ce centre.',
    'view_detail' => 'Voir',
    'metadata_title' => 'Informations complémentaires',
    'view_import' => 'Voir l\'import : :filename',
    'mark_resolved' => 'Marquer comme résolue',
    'resolving' => 'Résolution…',
    'types' => [
        'probable_duplicate' => 'Doublon probable',
        'reconciliation_failure' => 'Échec de rapprochement',
    ],
    'resolution' => [
        'open' => 'Ouverte',
        'resolved' => 'Résolue',
    ],
    'filters' => [
        'all_types' => 'Tous les types',
        'all_resolutions' => 'Tous les statuts',
        'open' => 'Ouvertes uniquement',
        'resolved' => 'Résolues uniquement',
        'from' => 'Du',
        'to' => 'Au',
    ],
    'columns' => [
        'type' => 'Type',
        'description' => 'Description',
        'status' => 'Statut',
        'detected_at' => 'Détectée',
        'import' => 'Import',
        'actions' => 'Actions',
    ],
    'detail' => [
        'detected_at' => 'Détectée le',
        'resolved_at' => 'Résolue le',
        'import' => 'Import associé',
    ],
    'resolve' => [
        'success' => 'Anomalie marquée comme résolue.',
        'not_allowed' => 'Vous n\'êtes pas autorisé à résoudre cette anomalie.',
        'already_resolved' => 'Cette anomalie est déjà résolue.',
    ],
];
