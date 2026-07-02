<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Models\CsvFormatVersion;
use App\Modules\CsvVerification\Models\HeaderAlias;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Support\CsvInspectionResult;
use App\Modules\CsvVerification\Support\HeaderNormalizer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CsvInspectionService
{
    public function inspectVerification(ImportVerification $verification): CsvInspectionResult
    {
        $disk = (string) config('csv_verification.temp_disk', 'local');

        if (! Storage::disk($disk)->exists($verification->temp_storage_path)) {
            throw new RuntimeException('Verification temp file is missing.');
        }

        $path = Storage::disk($disk)->path($verification->temp_storage_path);

        return $this->inspect($path);
    }

    public function inspect(string $filePath): CsvInspectionResult
    {
        $format = CsvFormatVersion::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($format === null) {
            throw new RuntimeException('No active CSV format version is configured.');
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return $this->failedResult($format, errors: [__('csv_verification.inspection.unreadable_file')]);
        }

        $errors = [];
        $warnings = [];

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $errors[] = __('csv_verification.inspection.invalid_encoding');
        }

        $hasBom = str_starts_with($contents, "\xEF\xBB\xBF");

        if (! $hasBom) {
            $errors[] = __('csv_verification.inspection.missing_bom');
        }

        $headerLine = $this->extractHeaderLine($contents);

        if ($headerLine === null) {
            return $this->failedResult(
                $format,
                encoding: 'UTF-8',
                hasBom: $hasBom,
                errors: array_merge($errors, [__('csv_verification.inspection.missing_header_row')]),
                warnings: $warnings,
            );
        }

        $delimiter = $this->detectDelimiter($headerLine, (string) $format->delimiter);

        if ($delimiter !== (string) $format->delimiter) {
            $errors[] = __('csv_verification.inspection.invalid_delimiter');
        }

        $headers = $this->parseHeaderRow($headerLine, $delimiter);
        $normalizedHeaders = array_map(
            static fn (string $header): string => HeaderNormalizer::normalize($header),
            $headers,
        );
        $columnCount = count($headers);

        if ($columnCount !== (int) $format->column_count) {
            $errors[] = __('csv_verification.inspection.invalid_column_count', [
                'count' => $format->column_count,
            ]);
        }

        $language = $this->detectLanguage($format, $normalizedHeaders);

        return new CsvInspectionResult(
            encoding: (string) $format->encoding,
            hasBom: $hasBom,
            delimiter: $delimiter,
            language: $language,
            headers: $headers,
            normalizedHeaders: $normalizedHeaders,
            columnCount: $columnCount,
            csvFormatVersionId: (int) $format->id,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * @param  list<string>  $normalizedHeaders
     */
    private function detectLanguage(CsvFormatVersion $format, array $normalizedHeaders): ?string
    {
        $aliases = HeaderAlias::query()
            ->where('csv_format_version_id', $format->id)
            ->where('is_active', true)
            ->get(['language', 'normalized_header']);

        /** @var array<string, list<string>> $languagesByNormalizedHeader */
        $languagesByNormalizedHeader = [];

        foreach ($aliases as $alias) {
            $languagesByNormalizedHeader[$alias->normalized_header][] = $alias->language;
        }

        $detectedLanguages = [];

        foreach ($normalizedHeaders as $normalizedHeader) {
            $languages = array_values(array_unique($languagesByNormalizedHeader[$normalizedHeader] ?? []));

            if (count($languages) !== 1) {
                continue;
            }

            $detectedLanguages[] = $languages[0];
        }

        $uniqueLanguages = array_values(array_unique($detectedLanguages));

        return count($uniqueLanguages) === 1 ? $uniqueLanguages[0] : null;
    }

    private function extractHeaderLine(string $contents): ?string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/u', '', $contents) ?? $contents;
        $contents = ltrim($contents, "\r\n");

        foreach (preg_split('/\R/u', $contents) ?: [] as $line) {
            if (trim($line) !== '') {
                return $line;
            }
        }

        return null;
    }

    private function detectDelimiter(string $headerLine, string $expectedDelimiter): string
    {
        $candidates = [';', ',', "\t", '|'];
        $bestDelimiter = $expectedDelimiter;
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($headerLine, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @return list<string>
     */
    private function parseHeaderRow(string $headerLine, string $delimiter): array
    {
        $headerLine = preg_replace('/^\x{FEFF}|\xEF\xBB\xBF/u', '', $headerLine) ?? $headerLine;
        $headers = str_getcsv($headerLine, $delimiter, '"', '\\');

        $headers = array_map(
            static fn (string $header): string => trim($header),
            $headers,
        );

        if ($headers !== [] && $headers[0] !== '') {
            $headers[0] = preg_replace('/^\x{FEFF}|\xEF\xBB\xBF/u', '', $headers[0]) ?? $headers[0];
        }

        return $headers;
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function failedResult(
        CsvFormatVersion $format,
        string $encoding = 'UTF-8',
        bool $hasBom = false,
        string $delimiter = ';',
        array $errors = [],
        array $warnings = [],
    ): CsvInspectionResult {
        return new CsvInspectionResult(
            encoding: $encoding,
            hasBom: $hasBom,
            delimiter: $delimiter,
            language: null,
            headers: [],
            normalizedHeaders: [],
            columnCount: 0,
            csvFormatVersionId: (int) $format->id,
            errors: $errors,
            warnings: $warnings,
        );
    }
}
