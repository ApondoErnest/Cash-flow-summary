<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Enums;

enum WhatsappEventType: string
{
    case ImportSuccess = 'import_success';
    case ImportWithDuplicates = 'import_with_duplicates';
    case DuplicateOnly = 'duplicate_only';
    case RevisionPending = 'revision_pending';
    case RevisionApproved = 'revision_approved';
    case ReconciliationMismatch = 'reconciliation_mismatch';
    case MissingSubmission = 'missing_submission';
    case DeliveryFailure = 'delivery_failure';
    case DailySummary = 'daily_summary';
    case WeeklySummary = 'weekly_summary';
    case MonthlySummary = 'monthly_summary';
    case YearlySummary = 'yearly_summary';
    case HistoricalImport = 'historical_import';
    case TestMessage = 'test_message';

    public function templateName(): string
    {
        if ($this === self::TestMessage) {
            return (string) config('whatsapp.test_template', 'hello_world');
        }

        if ($this->usesActivitySummaryTemplate()) {
            return (string) config('whatsapp.import_template', 'import_activity_summary');
        }

        return $this->value;
    }

    public function templateLanguageCode(): string
    {
        if ($this === self::TestMessage) {
            return (string) config('whatsapp.test_template_language', 'en_US');
        }

        if ($this->usesActivitySummaryTemplate()) {
            return (string) config('whatsapp.import_template_language', 'en');
        }

        return (string) config('whatsapp.default_language', 'en');
    }

    public function isScheduledSummary(): bool
    {
        return in_array($this, [
            self::DailySummary,
            self::WeeklySummary,
            self::MonthlySummary,
            self::YearlySummary,
        ], true);
    }

    public function isLegacyImportEvent(): bool
    {
        return in_array($this, [
            self::ImportSuccess,
            self::ImportWithDuplicates,
            self::DuplicateOnly,
            self::HistoricalImport,
        ], true);
    }

    public function usesActivitySummaryTemplate(): bool
    {
        return $this->isScheduledSummary() || $this->isLegacyImportEvent();
    }

    /**
     * @deprecated Use scheduledSummaryIdempotencyKey() for new messages.
     */
    public function usesSharedImportTemplate(): bool
    {
        return $this->usesActivitySummaryTemplate();
    }

    /**
     * @return list<string>|null
     */
    public function templateBodyParameterNames(): ?array
    {
        if (! $this->usesActivitySummaryTemplate()) {
            return null;
        }

        /** @var list<string> $names */
        $names = config('whatsapp.import_template_body_parameter_names', []);

        return $names !== [] ? $names : null;
    }
}
