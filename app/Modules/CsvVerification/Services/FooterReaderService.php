<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Support\CsvFooterDetector;
use App\Modules\CsvVerification\Support\FooterReadResult;
use App\Modules\CsvVerification\Support\FooterSummary;
use RuntimeException;

final class FooterReaderService
{
    /**
     * @param  array<string, int>  $mapping
     */
    public function readFile(string $filePath, string $delimiter, array $mapping): FooterReadResult
    {
        $footerLine = $this->findFooterLine($filePath);

        if ($footerLine === null) {
            return new FooterReadResult(
                summary: null,
                errors: [__('csv_verification.footer.missing')],
            );
        }

        return $this->parseFooterLine($footerLine, $delimiter, $mapping);
    }

    private function findFooterLine(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file for footer reading.');
        }

        try {
            $this->skipHeaderRow($handle);

            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                if (CsvFooterDetector::isFooterLine($line)) {
                    return $line;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * @param  array<string, int>  $mapping
     */
    private function parseFooterLine(string $line, string $delimiter, array $mapping): FooterReadResult
    {
        $cells = str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', '\\');
        $errors = [];

        $summary = $this->parseFooterCells($cells, $mapping, $errors);

        if ($summary === null) {
            if ($errors === []) {
                $errors[] = __('csv_verification.footer.invalid');
            }

            return new FooterReadResult(summary: null, errors: $errors);
        }

        return new FooterReadResult(summary: $summary);
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $mapping
     * @param  list<string>  $errors
     */
    private function parseFooterCells(array $cells, array $mapping, array &$errors): ?FooterSummary
    {
        $ht = $this->tryParseAmount($cells[$mapping['net_amount']] ?? '');
        $vat = $this->tryParseAmount($cells[$mapping['vat_amount']] ?? '');
        $ttc = $this->tryParseAmount($cells[$mapping['gross_amount']] ?? '');

        $amountIndices = array_values(array_filter([
            $mapping['net_amount'] ?? null,
            $mapping['vat_amount'] ?? null,
            $mapping['gross_amount'] ?? null,
        ], static fn (?int $index): bool => $index !== null));

        $count = $this->extractCount($cells, $amountIndices);

        if ($ht !== null && $vat !== null && $ttc !== null && $count !== null) {
            return new FooterSummary($count, $ht, $vat, $ttc);
        }

        return $this->parseFooterFromTrailingNumerics($cells, $errors);
    }

    /**
     * @param  list<string>  $cells
     * @param  list<int>  $amountIndices
     */
    private function extractCount(array $cells, array $amountIndices): ?int
    {
        $count = null;

        foreach ($cells as $index => $cell) {
            if (in_array($index, $amountIndices, true)) {
                continue;
            }

            $trimmed = trim($cell);

            if ($this->isPlainInteger($trimmed)) {
                $count = (int) $trimmed;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<string>  $errors
     */
    private function parseFooterFromTrailingNumerics(array $cells, array &$errors): ?FooterSummary
    {
        $numericCells = [];

        foreach ($cells as $index => $cell) {
            $trimmed = trim($cell);

            if ($trimmed === '') {
                continue;
            }

            if ($this->isPlainInteger($trimmed)) {
                $numericCells[] = [
                    'index' => $index,
                    'value' => (int) $trimmed,
                    'kind' => 'count',
                ];

                continue;
            }

            $amount = $this->tryParseAmount($trimmed);

            if ($amount !== null) {
                $numericCells[] = [
                    'index' => $index,
                    'value' => $amount,
                    'kind' => 'amount',
                ];
            }
        }

        if (count($numericCells) < 4) {
            $errors[] = __('csv_verification.footer.invalid');

            return null;
        }

        $lastFour = array_slice($numericCells, -4);

        return new FooterSummary(
            count: $lastFour[0]['value'],
            ht: $lastFour[1]['value'],
            vat: $lastFour[2]['value'],
            ttc: $lastFour[3]['value'],
        );
    }

    private function tryParseAmount(string $value): ?int
    {
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($value));

        if ($normalized === '' || ! preg_match('/^\d+$/', $normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function isPlainInteger(string $value): bool
    {
        return preg_match('/^\d+$/', trim($value)) === 1;
    }

    /**
     * @param  resource  $handle
     */
    private function skipHeaderRow($handle): void
    {
        if (fgets($handle) === false) {
            throw new RuntimeException('CSV file is missing a header row.');
        }
    }
}
