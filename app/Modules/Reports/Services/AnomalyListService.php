<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Enums\AnomalyType;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Support\AnomalyDetailData;
use App\Modules\Reports\Support\AnomalyListRow;
use App\Modules\Reports\Support\AnomalyStatusPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class AnomalyListService
{
    /**
     * @param  array{type?: string|null, resolution?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Anomaly>
     */
    public function paginateForActiveCenter(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Anomaly::query()
            ->with(['import:id,original_filename'])
            ->orderByDesc('created_at');

        if (($filters['type'] ?? '') !== '') {
            $query->where('type', $filters['type']);
        }

        if (($filters['resolution'] ?? '') === 'open') {
            $query->whereNull('resolved_at');
        } elseif (($filters['resolution'] ?? '') === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function toListRow(Anomaly $anomaly): AnomalyListRow
    {
        $anomaly->loadMissing(['import:id,original_filename']);
        $typeBadge = AnomalyStatusPresenter::typeBadge($anomaly->type);
        $resolutionBadge = AnomalyStatusPresenter::resolutionBadge($anomaly);

        return new AnomalyListRow(
            id: $anomaly->id,
            typeLabel: $typeBadge['label'],
            typeVariant: $typeBadge['variant'],
            description: $anomaly->description,
            resolutionLabel: $resolutionBadge['label'],
            resolutionVariant: $resolutionBadge['variant'],
            detectedAt: $this->formatTimestamp($anomaly->created_at),
            importFilename: $anomaly->import?->original_filename,
        );
    }

    public function toDetail(Anomaly $anomaly, bool $canResolve): AnomalyDetailData
    {
        $anomaly->loadMissing(['import:id,original_filename']);
        $typeBadge = AnomalyStatusPresenter::typeBadge($anomaly->type);
        $resolutionBadge = AnomalyStatusPresenter::resolutionBadge($anomaly);

        return new AnomalyDetailData(
            id: $anomaly->id,
            typeLabel: $typeBadge['label'],
            typeVariant: $typeBadge['variant'],
            description: $anomaly->description,
            resolutionLabel: $resolutionBadge['label'],
            resolutionVariant: $resolutionBadge['variant'],
            detectedAt: $this->formatTimestamp($anomaly->created_at),
            resolvedAt: $anomaly->resolved_at !== null
                ? $this->formatTimestamp($anomaly->resolved_at)
                : null,
            importId: $anomaly->import_id,
            importFilename: $anomaly->import?->original_filename,
            metadataRows: $this->metadataRows($anomaly->metadata ?? []),
            canResolve: $canResolve && $anomaly->resolved_at === null,
        );
    }

    /**
     * @return list<AnomalyType>
     */
    public function filterableTypes(): array
    {
        return AnomalyType::cases();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<array{label: string, value: string}>
     */
    private function metadataRows(array $metadata): array
    {
        $rows = [];

        foreach ($metadata as $key => $value) {
            $rows[] = [
                'label' => (string) $key,
                'value' => is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value, JSON_THROW_ON_ERROR),
            ];
        }

        return $rows;
    }

    private function formatTimestamp(?Carbon $timestamp): string
    {
        return $timestamp
            ?->timezone(config('app.timezone'))
            ->format('Y-m-d H:i') ?? '—';
    }
}
