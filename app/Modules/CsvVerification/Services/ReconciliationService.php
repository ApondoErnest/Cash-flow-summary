<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Support\FooterSummary;
use App\Modules\CsvVerification\Support\ParsedTotals;
use App\Modules\CsvVerification\Support\ReconciliationResult;

final class ReconciliationService
{
    public function __construct(
        private readonly CsvParsingService $csvParsingService,
    ) {}

    /**
     * @param  array<string, int>  $mapping
     */
    public function reconcile(
        string $filePath,
        string $delimiter,
        array $mapping,
        FooterSummary $footer,
    ): ReconciliationResult {
        $parsed = $this->sumValidRows($filePath, $delimiter, $mapping);

        $countMatches = $parsed->count === $footer->count;
        $htMatches = $parsed->ht === $footer->ht;
        $vatMatches = $parsed->vat === $footer->vat;
        $ttcMatches = $parsed->ttc === $footer->ttc;

        $errors = [];

        if (! $countMatches) {
            $errors[] = __('csv_verification.reconciliation.count_mismatch', [
                'footer' => $footer->count,
                'parsed' => $parsed->count,
            ]);
        }

        if (! $htMatches) {
            $errors[] = __('csv_verification.reconciliation.ht_mismatch', [
                'footer' => $footer->ht,
                'parsed' => $parsed->ht,
            ]);
        }

        if (! $vatMatches) {
            $errors[] = __('csv_verification.reconciliation.vat_mismatch', [
                'footer' => $footer->vat,
                'parsed' => $parsed->vat,
            ]);
        }

        if (! $ttcMatches) {
            $errors[] = __('csv_verification.reconciliation.ttc_mismatch', [
                'footer' => $footer->ttc,
                'parsed' => $parsed->ttc,
            ]);
        }

        return new ReconciliationResult(
            footer: $footer,
            parsed: $parsed,
            countMatches: $countMatches,
            htMatches: $htMatches,
            vatMatches: $vatMatches,
            ttcMatches: $ttcMatches,
            errors: $errors,
        );
    }

    /**
     * @param  array<string, int>  $mapping
     */
    public function summarizeValidRows(string $filePath, string $delimiter, array $mapping): ParsedTotals
    {
        return $this->sumValidRows($filePath, $delimiter, $mapping);
    }

    /**
     * @param  array<string, int>  $mapping
     */
    private function sumValidRows(string $filePath, string $delimiter, array $mapping): ParsedTotals
    {
        $count = 0;
        $ht = 0;
        $vat = 0;
        $ttc = 0;

        foreach ($this->csvParsingService->streamRows($filePath, $delimiter, $mapping) as $row) {
            if ($row->status === CsvRowStatus::Invalid) {
                continue;
            }

            $count++;
            $ht += $row->netAmount ?? 0;
            $vat += $row->vatAmount ?? 0;
            $ttc += $row->grossAmount ?? 0;
        }

        return new ParsedTotals($count, $ht, $vat, $ttc);
    }
}
