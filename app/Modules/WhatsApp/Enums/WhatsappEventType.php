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
    case HistoricalImport = 'historical_import';
    case TestMessage = 'test_message';

    public function templateName(): string
    {
        if ($this === self::TestMessage) {
            return (string) config('whatsapp.test_template', 'hello_world');
        }

        return $this->value;
    }
}
