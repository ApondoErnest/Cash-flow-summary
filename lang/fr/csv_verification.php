<?php

return [
    'inspection' => [
        'unreadable_file' => 'Impossible de lire le fichier CSV.',
        'invalid_encoding' => 'Le fichier doit être encodé en UTF-8.',
        'missing_bom' => 'Le fichier doit commencer par un marqueur d’ordre des octets UTF-8 (BOM).',
        'missing_header_row' => 'Le fichier ne contient pas de ligne d’en-tête.',
        'invalid_delimiter' => 'Le fichier doit utiliser un point-virgule (;) comme séparateur de colonnes.',
        'invalid_column_count' => 'La ligne d’en-tête doit contenir exactement :count colonnes.',
    ],
    'mapping' => [
        'inspection_required' => 'L’inspection CSV doit réussir avant de mapper les en-têtes.',
        'mixed_language' => 'Le fichier mélange des en-têtes français et anglais. Utilisez une seule langue.',
        'language_undetermined' => 'La langue du fichier n’a pas pu être déterminée à partir de la ligne d’en-tête.',
        'unknown_headers' => 'En-tête(s) inconnu(s) : :headers.',
        'missing_required_field' => 'Colonne obligatoire manquante : :field.',
        'duplicate_canonical_field' => 'Colonne mappée en double pour :field.',
    ],
    'parsing' => [
        'invalid_registration_date' => 'La date d’enregistrement est manquante ou invalide.',
        'invalid_date' => 'Date invalide dans :field.',
        'invalid_time' => 'Heure invalide dans :field.',
        'invalid_amount' => 'Montant invalide dans :field.',
        'negative_amount' => 'Montant négatif dans :field.',
        'amount_mismatch' => 'Les montants de la ligne ne respectent pas HT + TVA = TTC.',
    ],
    'footer' => [
        'missing' => 'Le fichier ne contient pas de ligne de total en pied de page.',
        'invalid' => 'La ligne de total en pied de page n’a pas pu être analysée.',
    ],
    'reconciliation' => [
        'count_mismatch' => 'Le nombre d’enregistrements ne correspond pas au pied de page (pied de page : :footer, analysé : :parsed).',
        'ht_mismatch' => 'Le montant hors taxe ne correspond pas au pied de page (pied de page : :footer, analysé : :parsed).',
        'vat_mismatch' => 'Le montant de la TVA ne correspond pas au pied de page (pied de page : :footer, analysé : :parsed).',
        'ttc_mismatch' => 'Le montant TTC ne correspond pas au pied de page (pied de page : :footer, analysé : :parsed).',
    ],
    'verification' => [
        'expired' => 'Cette vérification a expiré. Téléversez à nouveau le fichier.',
        'reject_not_allowed' => 'Cette vérification ne peut pas être rejetée dans son état actuel.',
    ],
];
