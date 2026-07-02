<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Models\HeaderAlias;
use App\Modules\CsvVerification\Support\CsvInspectionResult;
use App\Modules\CsvVerification\Support\HeaderMappingResult;
use Illuminate\Support\Collection;

final class HeaderMappingService
{
    public function map(CsvInspectionResult $inspection): HeaderMappingResult
    {
        if (! $inspection->isValid()) {
            return new HeaderMappingResult(
                language: null,
                mapping: [],
                errors: [__('csv_verification.mapping.inspection_required')],
            );
        }

        return $this->mapHeaders(
            $inspection->csvFormatVersionId,
            $inspection->headers,
            $inspection->normalizedHeaders,
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $normalizedHeaders
     */
    public function mapHeaders(int $csvFormatVersionId, array $headers, array $normalizedHeaders): HeaderMappingResult
    {
        $aliases = HeaderAlias::query()
            ->where('csv_format_version_id', $csvFormatVersionId)
            ->where('is_active', true)
            ->get();

        $languageSignals = $this->collectLanguageSignals($aliases, $normalizedHeaders);

        if ($languageSignals['fr'] > 0 && $languageSignals['en'] > 0) {
            return new HeaderMappingResult(
                language: null,
                mapping: [],
                isMixedLanguage: true,
                errors: [__('csv_verification.mapping.mixed_language')],
            );
        }

        $language = match (true) {
            $languageSignals['fr'] > 0 => 'fr',
            $languageSignals['en'] > 0 => 'en',
            default => null,
        };

        if ($language === null) {
            return new HeaderMappingResult(
                language: null,
                mapping: [],
                errors: [__('csv_verification.mapping.language_undetermined')],
            );
        }

        $mapping = [];
        $unknownHeaders = [];
        $suggestions = [];
        $errors = [];

        foreach ($normalizedHeaders as $index => $normalizedHeader) {
            $sourceHeader = $headers[$index] ?? $normalizedHeader;
            $alias = $aliases->first(
                fn (HeaderAlias $candidate): bool => $candidate->language === $language
                    && $candidate->normalized_header === $normalizedHeader,
            );

            if ($alias === null) {
                $unknownHeaders[] = $sourceHeader;
                $suggestions[$sourceHeader] = $this->suggestMatches($normalizedHeader, $aliases);

                continue;
            }

            if (array_key_exists($alias->canonical_field, $mapping)) {
                $errors[] = __('csv_verification.mapping.duplicate_canonical_field', [
                    'field' => $alias->canonical_field,
                ]);
            }

            $mapping[$alias->canonical_field] = $index;
        }

        if ($unknownHeaders !== []) {
            $errors[] = __('csv_verification.mapping.unknown_headers', [
                'headers' => implode(', ', $unknownHeaders),
            ]);
        }

        $requiredFields = $aliases
            ->where('language', $language)
            ->where('is_required', true)
            ->pluck('canonical_field')
            ->unique()
            ->values();

        foreach ($requiredFields as $requiredField) {
            if (! array_key_exists($requiredField, $mapping)) {
                $errors[] = __('csv_verification.mapping.missing_required_field', [
                    'field' => $requiredField,
                ]);
            }
        }

        return new HeaderMappingResult(
            language: $language,
            mapping: $mapping,
            errors: $errors,
            unknownHeaders: $unknownHeaders,
            suggestions: $suggestions,
        );
    }

    /**
     * @param  Collection<int, HeaderAlias>  $aliases
     * @param  list<string>  $normalizedHeaders
     * @return array{fr: int, en: int}
     */
    private function collectLanguageSignals(Collection $aliases, array $normalizedHeaders): array
    {
        $signals = ['fr' => 0, 'en' => 0];

        foreach ($normalizedHeaders as $normalizedHeader) {
            $languages = $aliases
                ->where('normalized_header', $normalizedHeader)
                ->pluck('language')
                ->unique()
                ->values();

            if ($languages->count() !== 1) {
                continue;
            }

            $language = $languages->first();

            if ($language === 'fr' || $language === 'en') {
                $signals[$language]++;
            }
        }

        return $signals;
    }

    /**
     * @param  Collection<int, HeaderAlias>  $aliases
     * @return list<string>
     */
    private function suggestMatches(string $normalizedHeader, Collection $aliases): array
    {
        $suggestions = [];

        foreach ($aliases->unique('canonical_field') as $alias) {
            similar_text($normalizedHeader, $alias->normalized_header, $percent);

            if ($percent >= 55) {
                $suggestions[] = $alias->canonical_field;
            }
        }

        return array_values(array_unique($suggestions));
    }
}
