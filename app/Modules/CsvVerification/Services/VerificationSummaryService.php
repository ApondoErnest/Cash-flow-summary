<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Support\VerificationSummaryCheck;
use App\Modules\CsvVerification\Support\VerificationSummaryData;
use App\Modules\Dashboards\Support\DashboardMoney;
use Illuminate\Support\Carbon;

final class VerificationSummaryService
{
    public function build(ImportVerification $verification): VerificationSummaryData
    {
        $footer = $verification->footer_summary ?? [];
        $rowStats = $verification->row_stats ?? [];
        $duplicateSummary = $verification->duplicate_summary ?? [];
        $validation = $verification->validation_result ?? [];

        $completed = (int) ($rowStats['completed'] ?? 0);
        $zeroValue = (int) ($rowStats['zero'] ?? 0);

        $checks = [
            new VerificationSummaryCheck(
                key: 'structure',
                label: __('csv_verification.summary.checks.structure'),
                passed: ($validation['inspection']['valid'] ?? false) === true
                    && ($validation['header_mapping']['valid'] ?? false) === true,
            ),
            new VerificationSummaryCheck(
                key: 'count',
                label: __('csv_verification.summary.checks.count'),
                passed: ($validation['reconciliation']['count']['passed'] ?? false) === true,
            ),
            new VerificationSummaryCheck(
                key: 'ht',
                label: __('csv_verification.summary.checks.ht'),
                passed: ($validation['reconciliation']['ht']['passed'] ?? false) === true,
            ),
            new VerificationSummaryCheck(
                key: 'vat',
                label: __('csv_verification.summary.checks.vat'),
                passed: ($validation['reconciliation']['vat']['passed'] ?? false) === true,
            ),
            new VerificationSummaryCheck(
                key: 'ttc',
                label: __('csv_verification.summary.checks.ttc'),
                passed: ($validation['reconciliation']['ttc']['passed'] ?? false) === true,
            ),
        ];

        return new VerificationSummaryData(
            filename: $verification->original_filename,
            centerName: $verification->center->name,
            sourceLanguage: strtoupper((string) ($verification->source_language ?? 'fr')),
            reportedPeriod: $verification->reported_period,
            actualPeriod: $this->formatActualPeriod($verification),
            footerCount: number_format((int) ($footer['count'] ?? 0), 0, '', ' '),
            footerHt: DashboardMoney::format((int) ($footer['ht'] ?? 0)),
            footerVat: DashboardMoney::format((int) ($footer['vat'] ?? 0)),
            footerTtc: DashboardMoney::format((int) ($footer['ttc'] ?? 0)),
            checks: $checks,
            completed: $completed,
            unfinished: (int) ($rowStats['unfinished'] ?? 0),
            revenueGenerating: max(0, $completed - $zeroValue),
            zeroValue: $zeroValue,
            invalidRows: (int) ($rowStats['invalid'] ?? 0),
            exactDuplicates: (int) ($duplicateSummary['exact'] ?? 0),
            newUnique: (int) ($duplicateSummary['new_unique'] ?? 0),
            probableDuplicates: (int) ($duplicateSummary['probable'] ?? 0),
            warnings: $this->buildWarnings($verification, $rowStats, $duplicateSummary),
            canImport: collect($checks)->every(static fn (VerificationSummaryCheck $check): bool => $check->passed),
        );
    }

    private function formatActualPeriod(ImportVerification $verification): ?string
    {
        if ($verification->actual_period_start === null || $verification->actual_period_end === null) {
            return null;
        }

        $start = Carbon::parse($verification->actual_period_start)->format('d/m/Y');
        $end = Carbon::parse($verification->actual_period_end)->format('d/m/Y');

        return $start === $end ? $start : "{$start} – {$end}";
    }

    /**
     * @param  array<string, mixed>  $rowStats
     * @param  array<string, mixed>  $duplicateSummary
     * @return list<string>
     */
    private function buildWarnings(
        ImportVerification $verification,
        array $rowStats,
        array $duplicateSummary,
    ): array {
        $warnings = [];

        if (((int) ($duplicateSummary['exact'] ?? 0)) > 0) {
            $warnings[] = trans_choice(
                'csv_verification.summary.warnings.exact_duplicates',
                (int) $duplicateSummary['exact'],
                ['count' => (int) $duplicateSummary['exact']],
            );
        }

        if (((int) ($duplicateSummary['probable'] ?? 0)) > 0) {
            $warnings[] = trans_choice(
                'csv_verification.summary.warnings.probable_duplicates',
                (int) $duplicateSummary['probable'],
                ['count' => (int) $duplicateSummary['probable']],
            );
        }

        if (((int) ($rowStats['unfinished'] ?? 0)) > 0) {
            $warnings[] = trans_choice(
                'csv_verification.summary.warnings.unfinished_rows',
                (int) $rowStats['unfinished'],
                ['count' => (int) $rowStats['unfinished']],
            );
        }

        if (((int) ($rowStats['zero'] ?? 0)) > 0) {
            $warnings[] = trans_choice(
                'csv_verification.summary.warnings.zero_value_rows',
                (int) $rowStats['zero'],
                ['count' => (int) $rowStats['zero']],
            );
        }

        if (((int) ($rowStats['invalid'] ?? 0)) > 0) {
            $warnings[] = trans_choice(
                'csv_verification.summary.warnings.invalid_rows',
                (int) $rowStats['invalid'],
                ['count' => (int) $rowStats['invalid']],
            );
        }

        if ($verification->reported_period !== null
            && $verification->actual_period_start !== null
            && $verification->actual_period_end !== null
            && ! str_contains((string) $verification->reported_period, $verification->actual_period_start->format('Y-m'))) {
            $warnings[] = __('csv_verification.summary.warnings.period_mismatch');
        }

        return $warnings;
    }
}
