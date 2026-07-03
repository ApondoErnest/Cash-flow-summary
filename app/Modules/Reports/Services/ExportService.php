<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Center;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Enums\ReportType;
use App\Modules\Reports\Jobs\GenerateExportJob;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Support\CenterReportData;
use App\Modules\Reports\Support\CenterReportExportBuilder;
use App\Modules\Reports\Support\ExportListRow;
use App\Modules\Reports\Support\ExportRequestStatusPresenter;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ExportService
{
    public function __construct(
        private readonly ReportQueryService $reportQueryService,
        private readonly CenterReportExportBuilder $exportBuilder,
    ) {}

    public function requestCenterReportExport(
        User $user,
        Center $center,
        ExportFormat $format,
        DashboardPeriod $period,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
    ): ExportRequest {
        $filters = $this->filtersForPeriod($period, $customFrom, $customTo);

        $export = DB::transaction(function () use ($user, $center, $format, $filters): ExportRequest {
            $export = ExportRequest::query()->create([
                'user_id' => $user->id,
                'center_id' => $center->id,
                'report_type' => ReportType::CenterReport->value,
                'filters' => $filters,
                'format' => $format,
                'status' => ExportRequestStatus::Pending,
            ]);

            AuditLog::query()->create([
                'user_id' => $user->id,
                'center_id' => $center->id,
                'event' => 'export.requested',
                'resource_type' => ExportRequest::class,
                'resource_id' => $export->id,
                'new_values' => [
                    'report_type' => ReportType::CenterReport->value,
                    'format' => $format->value,
                    'filters' => $filters,
                ],
            ]);

            return $export;
        });

        GenerateExportJob::dispatch($export->id);

        return $export;
    }

    public function isDownloadable(ExportRequest $export): bool
    {
        if ($export->status !== ExportRequestStatus::Completed) {
            return false;
        }

        if ($export->isExpired()) {
            return false;
        }

        if ($export->storage_path === null || $export->storage_path === '') {
            return false;
        }

        return Storage::disk((string) config('exports.disk', 'local'))->exists($export->storage_path);
    }

    public function downloadResponse(ExportRequest $export): Response
    {
        if (! $this->isDownloadable($export)) {
            abort(404);
        }

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'center_id' => $export->center_id,
            'event' => 'export.downloaded',
            'resource_type' => ExportRequest::class,
            'resource_id' => $export->id,
            'new_values' => [
                'format' => $export->format->value,
                'report_type' => $export->report_type,
            ],
        ]);

        $disk = (string) config('exports.disk', 'local');
        $filename = $this->downloadFilename($export);

        return response(
            Storage::disk($disk)->get((string) $export->storage_path),
            200,
            [
                'Content-Type' => $this->exportBuilder->mimeType($export->format),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    public function downloadFilename(ExportRequest $export): string
    {
        $period = (string) ($export->filters['period'] ?? 'report');
        $centerSlug = Str::slug($export->center?->code ?? 'center');

        return sprintf(
            'center-report-%s-%s.%s',
            $centerSlug,
            $period,
            $export->format->value,
        );
    }

    /**
     * @return Collection<int, ExportListRow>
     */
    public function recentExportsForCenter(User $user, Center $center, int $limit = 10): Collection
    {
        $query = ExportRequest::query()
            ->where('center_id', $center->id)
            ->latest('id');

        if (! $user->isOwner()) {
            $query->where('user_id', $user->id);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (ExportRequest $export): ExportListRow => $this->presentExport($export));
    }

    public function presentExport(ExportRequest $export): ExportListRow
    {
        $badge = ExportRequestStatusPresenter::badge($export);
        $period = DashboardPeriod::tryFrom((string) ($export->filters['period'] ?? DashboardPeriod::Month->value))
            ?? DashboardPeriod::Month;
        $customFrom = isset($export->filters['from'])
            ? Carbon::parse((string) $export->filters['from'])
            : null;
        $customTo = isset($export->filters['to'])
            ? Carbon::parse((string) $export->filters['to'])
            : null;

        return new ExportListRow(
            id: $export->id,
            formatLabel: __('reports.export.formats.'.$export->format->value),
            periodLabel: $period->label($customFrom, $customTo),
            statusLabel: $badge['label'],
            statusVariant: $badge['variant'],
            downloadUrl: $this->isDownloadable($export)
                ? route('exports.download', $export)
                : null,
            isInProgress: in_array($export->status, [ExportRequestStatus::Pending, ExportRequestStatus::Processing], true),
            requestedAt: $export->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
            expiresAt: $export->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        );
    }

    public function generate(ExportRequest $export): void
    {
        if ($export->status !== ExportRequestStatus::Pending && $export->status !== ExportRequestStatus::Processing) {
            return;
        }

        $export->update(['status' => ExportRequestStatus::Processing]);

        try {
            $report = $this->buildReportFromExport($export);
            $contents = $this->exportBuilder->build($report, $export->format);
            $path = $this->storeExport($export, $contents);

            $export->update([
                'status' => ExportRequestStatus::Completed,
                'storage_path' => $path,
                'expires_at' => now()->addHours((int) config('exports.ttl_hours', 6)),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $export->update(['status' => ExportRequestStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersForPeriod(
        DashboardPeriod $period,
        ?Carbon $customFrom,
        ?Carbon $customTo,
    ): array {
        $filters = [
            'period' => $period->value,
        ];

        if ($period === DashboardPeriod::Custom) {
            $filters['from'] = $customFrom?->toDateString();
            $filters['to'] = $customTo?->toDateString();
        }

        return $filters;
    }

    private function buildReportFromExport(ExportRequest $export): CenterReportData
    {
        $center = Center::query()->findOrFail($export->center_id);
        $period = DashboardPeriod::tryFrom((string) ($export->filters['period'] ?? DashboardPeriod::Month->value))
            ?? DashboardPeriod::Month;

        $customFrom = null;
        $customTo = null;

        if ($period === DashboardPeriod::Custom) {
            $customFrom = isset($export->filters['from'])
                ? Carbon::parse((string) $export->filters['from'])->startOfDay()
                : null;
            $customTo = isset($export->filters['to'])
                ? Carbon::parse((string) $export->filters['to'])->endOfDay()
                : null;
        }

        return $this->reportQueryService->buildCenterReport(
            center: $center,
            period: $period,
            customFrom: $customFrom,
            customTo: $customTo,
        );
    }

    private function storeExport(ExportRequest $export, string $contents): string
    {
        $disk = (string) config('exports.disk', 'local');
        $directory = trim((string) config('exports.directory', 'exports'), '/');
        $extension = $this->exportBuilder->extension($export->format);
        $filename = sprintf(
            'center-report-%d-%s.%s',
            $export->id,
            Str::lower(Str::random(8)),
            $extension,
        );
        $path = $directory.'/'.$export->center_id.'/'.$filename;

        Storage::disk($disk)->put($path, $contents);

        return $path;
    }
}
