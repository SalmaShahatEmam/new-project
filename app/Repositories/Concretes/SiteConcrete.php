<?php

namespace App\Repositories\Concretes;

use App\Enum\SiteStatusEnum;
use App\Models\Resource;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Repositories\Contracts\SiteContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SiteConcrete extends BaseConcrete implements SiteContract
{
    /**
     * SiteConcrete constructor.
     * @param Site $model
     */
    public function __construct(Site $model)
    {
        parent::__construct($model);
    }

    public function createSite(array $attributes, array $userIds): Site
    {
        return DB::transaction(function () use ($attributes, $userIds): Site {
            $attributes['status'] = $userIds === []
                ? SiteStatusEnum::WAIT_ASSIGN->value
                : SiteStatusEnum::IN_PROGRESS->value;

            /** @var Site $site */
            $site = $this->create($attributes);
            $site->users()->sync($userIds);

            return $site;
        });
    }

    public function updateSite(Site $site, array $attributes, ?array $userIds): Site
    {
        return DB::transaction(function () use ($site, $attributes, $userIds): Site {
            if ($attributes !== []) {
                /** @var Site $site */
                $site = $this->update($site, $attributes);
            }

            if ($userIds !== null) {
                $site->users()->sync($userIds);
            }

            return $site;
        });
    }

    public function getSiteDetails(Site $site, ?string $date = null , array $duration = []): array
    {
        $reportsQuery = $site->reports()->with(['user', 'resource', 'supplier']);
        $problemsQuery = $site->problems()->with(['user', 'founder', 'assignee']);

        if ($date !== null) {
            $reportsQuery->whereDate('created_at', $date);
            $problemsQuery->whereDate('created_at', $date);
        }

          if (! empty($from) && ! empty($to)) {
                $reportsQuery->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            } elseif (! empty($from)) {
                $reportsQuery->whereDate('created_at', '>=', $from);
            } elseif (! empty($to)) {
                $reportsQuery->whereDate('created_at', '<=', $to);
            } elseif (! empty($date) && $date !== 'all') {
                $reportsQuery->whereDate('created_at', $date);
            }

        return [
            'reports' => $reportsQuery->latest()->get(),
            'problems' => $problemsQuery->latest()->get(),
        ];
    }

    
public function updateSiteReports(
  
    array $attributes,
    int $userId
): Collection {
    return DB::transaction(function () use (
        $attributes,
        $userId
    ): Collection {
        $site = Site::findOrFail($attributes['site_id']);

        // Prevent updating a report through the wrong site.
        if ((int) $site->id !== (int) $attributes['site_id']) {
            throw ValidationException::withMessages([
                'site_id' => 'The report does not belong to this site.',
            ]);
        }

        $reportItems = $attributes['resources'] ?? [];

        $reportType = $attributes['report_type'] ?? null;

        foreach ($reportItems as $item) {

              if (! is_null($item['report_id'])) {
                // Existing report: update only if it belongs to this site.
                $existingReport = $site->reports()
                    ->where('id', $item['report_id'])
                    ->first();
                $itemData = [
                'user_id' => $userId,
                'resource_id' => $item['resource_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ];

            if (array_key_exists('tax', $item)) {
                $itemData['tax'] = $item['tax'];
            }

            if (array_key_exists('supplier_id', $item)) {
                $itemData['supplier_id'] = $item['supplier_id'];
            }

            if (array_key_exists('report_content', $item)) {
                $itemData['report_content'] = $item['report_content'];
            } elseif (array_key_exists('report_content', $attributes)) {
                $itemData['report_content'] = $attributes['report_content'];
            }

            if (array_key_exists('notes', $item)) {
                $itemData['notes'] = $item['notes'];
            } elseif (array_key_exists('notes', $attributes)) {
                $itemData['notes'] = $attributes['notes'];
            }

                $existingReport->update($itemData);

            } else {
                $resource = Resource::findOrFail($item['resource_id']);

                
            $data =  [
                'user_id' => $userId,
                'resource_id' => $resource->id,
                'quantity' => $item['quantity'] ?? null,
                'price' => $resource->latest_price,
                'tax' => $resource->latest_tax,
                'supplier_id' => $item['supplier_id'] ?? null,
                'report_content' => $item['report_content'] ?? $attributes['report_content'] ?? null,
                'notes' => $item['notes'] ?? $attributes['notes'] ?? null,
                'report_type' => $attributes['report_type'],
               
            ];
               $report = $site->reports()->create($data);
               $report->update(['created_at' => $attributes['report_date']]);
        }
          
        }

        return $site->reports()
            ->with(['user', 'resource', 'supplier'])
            ->latest()
            ->get();
    });
}


    public function getReportDetails(SiteReport $report): SiteReport
    {
        return $report->load(['site', 'user', 'resource', 'supplier']);
    }

    public function deleteSiteReport(SiteReport $report): void
    {
        if (! $report->delete()) {
            throw new RuntimeException('Unable to delete site report.');
        }
    }

    public function deleteSiteReportsByFilters(array $filters): int
    {
        $query = SiteReport::query()->whereDate('created_at', $filters['created_at']);

        if (isset($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->delete();
    }

    public function searchSites(
        array $filters = [],
        bool $applyOrder = true,
        bool $page = true,
        int $limit = self::LIMIT,
        string $orderBy = self::ORDER_BY,
        string $orderDir = self::ORDER_DIR
    ): mixed {
        $query = $this->model->newQuery();
        $status = $filters['status'] ?? null;
        unset($filters['status']);

        if (isset($filters['has_reports']) || isset($filters['report_date']) || isset($filters['reports_date'])) {
            $reportDate = $filters['report_date'] ?? $filters['reports_date'] ?? null;
            if ($reportDate === 'today') {
                $reportDate = now()->toDateString();
            }
            $query->whereHas('reports', function ($q) use ($reportDate) {
                if ($reportDate && $reportDate !== 'all') {
                    $q->whereDate('created_at', $reportDate);
                }
            });
            unset($filters['has_reports'], $filters['report_date'], $filters['reports_date']);
        }

        $query = $this->baseSearch($query, $filters);
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->getQueryResult($query, $applyOrder, $page, $limit, $orderBy, $orderDir);
    }

    public function deleteSite(Site $site): void
    {
        $attachmentPaths = DB::transaction(function () use ($site): array {
            $attachmentPaths = $site->problems()
                ->get()
                ->flatMap(fn (SiteProblem $problem) => collect($problem->attachments ?? [])->pluck('path'))
                ->filter()
                ->values()
                ->all();

            $site->users()->detach();
            $site->reports()->delete();
            $site->problems()->delete();
            $this->remove($site);

            return $attachmentPaths;
        });

        if ($attachmentPaths !== [] && ! Storage::disk('public')->delete($attachmentPaths)) {
            throw new RuntimeException('Unable to delete site problem attachments.');
        }
    }

    public function expandSiteDuration(Site $site, int $durationInMonths): Site
    {
       $site->duration_in_months += $durationInMonths;
       $attributes['duration_in_months'] = $site->duration_in_months;
        if (in_array($site->status, [
            SiteStatusEnum::FINISHED->value,
            SiteStatusEnum::DEADLINE_PASSED->value,
        ], true)) {
            $attributes['status'] = SiteStatusEnum::IN_PROGRESS->value;
        }

        /** @var Site */
        return $this->update($site, $attributes);
    }

    public function syncSiteUsers(int $siteId, array $userIds): Site
    {
        $site = $this->findOrFail($siteId);

        return DB::transaction(function () use ($site, $userIds): Site {
            $site->users()->sync($userIds);

            return $site->load('users');
        });
    }

    public function removeSiteUser(int $siteId, int $userId): Site
    {
        $site = $this->findOrFail($siteId);

        DB::transaction(fn () => $site->users()->detach($userId));

        return $site->fresh('users');
    }

    public function updateSiteStatus(Site $site, string $status): Site
    {
        /** @var Site */
        return $this->update($site, ['status' => $status]);
    }

    public function addSiteReports(array $attributes, int $userId): Collection
    {
        $siteId = $attributes['site_id'];
        $reportItems = $attributes['resources'] ?? [$attributes];
        unset($attributes['resources']);

        $resourceIds = collect($reportItems)
            ->pluck('resource_id')
            ->filter()
            ->unique()
            ->values();

        if ($resourceIds->isNotEmpty()) {
            Resource::query()
                ->without(['category', 'unit', 'resourcePrices'])
                ->findOrFail($resourceIds->all());
        }

        $latestPrices = $resourceIds->isEmpty()
            ? collect()
            : DB::table('resource_prices')
                ->whereIn('resource_id', $resourceIds)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['resource_id', 'price', 'tax_value'])
                ->unique('resource_id')
                ->keyBy('resource_id');

        $reportData = array_map(function (array $item) use ($attributes, $latestPrices, $userId): array {
            $resourceId = $item['resource_id'] ?? null;
            $latestPrice = $resourceId ? $latestPrices->get($resourceId) : null;

            return [
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'quantity' => $item['quantity'] ?? null,
                'price' => $latestPrice->price ?? $item['price'] ?? null,
                'tax' => $latestPrice->tax_value ?? $item['tax'] ?? null,
                'supplier_id' => $item['supplier_id'] ?? null,
                'report_content' => $item['report_content'] ?? $attributes['report_content'] ?? null,
                'notes' => $item['notes'] ?? $attributes['notes'] ?? null,
                'report_type' => $attributes['report_type'],
            ];
        }, $reportItems);

        return DB::transaction(
            fn (): Collection => $this->findOrFail($siteId)->reports()->createMany($reportData)
        );
    }

    public function addSiteProblem(Site $site, array $attributes, array $files): SiteProblem
    {
        return DB::transaction(function () use ($site, $attributes, $files): SiteProblem {
            $attachments = [];

            foreach ($files as $file) {
                $path = $file->store('site-problems', 'public');

                if ($path === false) {
                    throw new RuntimeException('Unable to store a site problem attachment.');
                }

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            return $site->problems()->create([
                ...$attributes,
                'status' => $attributes['status'] ?? 'new',
                'attachments' => $attachments,
            ]);
        });
    }

    public function getSiteProblems(Site $site): Collection
    {
        return $site->problems()
            ->with(['site', 'user', 'founder', 'assignee'])
            ->latest()
            ->get();
    }

    public function updateSiteProblem(SiteProblem $problem, array $attributes, array $files = []): SiteProblem
    {
        return DB::transaction(function () use ($problem, $attributes, $files): SiteProblem {
            $attachments = $problem->attachments ?? [];
            $deletedFilePaths = [];

            // Handle dropping all attachments if requested
            if (isset($attributes['drop_all_attachments']) && filter_var($attributes['drop_all_attachments'], FILTER_VALIDATE_BOOLEAN)) {
                foreach ($attachments as $att) {
                    if (! empty($att['path'])) {
                        $deletedFilePaths[] = $att['path'];
                    }
                }
                $attachments = [];
            } elseif (! empty($attributes['drop_attachments']) || ! empty($attributes['remove_attachments']) || ! empty($attributes['delete_attachments'])) {
                $toDrop = array_merge(
                    (array) ($attributes['drop_attachments'] ?? []),
                    (array) ($attributes['remove_attachments'] ?? []),
                    (array) ($attributes['delete_attachments'] ?? [])
                );

                $remainingAttachments = [];
                foreach ($attachments as $att) {
                    $attPath = $att['path'] ?? '';
                    $attName = $att['name'] ?? '';
                    $attBasename = basename($attPath);

                    $shouldDrop = in_array($attPath, $toDrop, true)
                        || in_array($attName, $toDrop, true)
                        || in_array($attBasename, $toDrop, true);

                    if ($shouldDrop) {
                        $deletedFilePaths[] = $attPath;
                    } else {
                        $remainingAttachments[] = $att;
                    }
                }
                $attachments = array_values($remainingAttachments);
            }

            // Handle new uploaded files
            foreach ($files as $file) {
                $path = $file->store('site-problems', 'public');

                if ($path === false) {
                    throw new RuntimeException('Unable to store a site problem attachment.');
                }

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            // Handle suggestions
            $suggestedSolutions = $problem->suggested_solutions ?? [];
            if (array_key_exists('suggested_solutions', $attributes)) {
                $suggestedSolutions = is_array($attributes['suggested_solutions'])
                    ? $attributes['suggested_solutions']
                    : (json_decode($attributes['suggested_solutions'], true) ?? []);
            }

            if (! empty($attributes['drop_suggested_solutions']) || ! empty($attributes['remove_suggested_solutions'])) {
                $dropSolutions = array_merge(
                    (array) ($attributes['drop_suggested_solutions'] ?? []),
                    (array) ($attributes['remove_suggested_solutions'] ?? [])
                );

                $suggestedSolutions = array_values(array_filter(
                    $suggestedSolutions,
                    function ($sol, $index) use ($dropSolutions) {
                        return ! in_array($sol, $dropSolutions, true)
                            && ! in_array($index, $dropSolutions, true)
                            && ! in_array((string) $index, $dropSolutions, true);
                    },
                    ARRAY_FILTER_USE_BOTH
                ));
            }

            if (! empty($attributes['add_suggested_solutions'])) {
                $addSolutions = (array) $attributes['add_suggested_solutions'];
                foreach ($addSolutions as $sol) {
                    if (is_string($sol) && trim($sol) !== '') {
                        $suggestedSolutions[] = trim($sol);
                    }
                }
                $suggestedSolutions = array_values(array_unique($suggestedSolutions));
            }

            $fillable = [
                'explanation',
                'causes',
                'notes',
                'status',
                'priority',
                'assigned_to',
                'founded_by',
            ];

            $updateData = [];
            foreach ($fillable as $field) {
                if (array_key_exists($field, $attributes)) {
                    $updateData[$field] = $attributes[$field];
                }
            }

            $hasAttachmentChanges = $files !== []
                || ! empty($attributes['drop_attachments'])
                || ! empty($attributes['remove_attachments'])
                || ! empty($attributes['delete_attachments'])
                || (isset($attributes['drop_all_attachments']) && filter_var($attributes['drop_all_attachments'], FILTER_VALIDATE_BOOLEAN));

            if ($hasAttachmentChanges) {
                $updateData['attachments'] = $attachments;
            }

            $hasSolutionChanges = array_key_exists('suggested_solutions', $attributes)
                || ! empty($attributes['drop_suggested_solutions'])
                || ! empty($attributes['remove_suggested_solutions'])
                || ! empty($attributes['add_suggested_solutions']);

            if ($hasSolutionChanges) {
                $updateData['suggested_solutions'] = array_values($suggestedSolutions);
            }

            if ($updateData !== []) {
                $problem->update($updateData);
            }

            if ($deletedFilePaths !== []) {
                Storage::disk('public')->delete(array_values(array_filter($deletedFilePaths)));
            }

            return $problem->fresh(['site', 'user', 'founder', 'assignee']);
        });
    }

    public function deleteSiteProblem(SiteProblem $problem): void
    {
        $attachmentPaths = collect($problem->attachments ?? [])
            ->pluck('path')
            ->filter()
            ->values()
            ->all();

        if (! $problem->delete()) {
            throw new RuntimeException('Unable to delete site problem.');
        }

        if ($attachmentPaths !== []) {
            Storage::disk('public')->delete($attachmentPaths);
        }
    }

    public function getReportsAndProblemsByDate(array $attributes): array
    {
        $date = $attributes['date'];
        $siteId = $attributes['site_id'] ?? null;

        $reportsQuery = SiteReport::query()
            ->with(['site', 'user', 'resource', 'supplier'])
            ->whereDate('created_at', $date);

        $problemsQuery = SiteProblem::query()
            ->with(['site', 'user', 'founder', 'assignee']);

        if ($siteId !== null) {
            $reportsQuery->where('site_id', $siteId);
            $problemsQuery->where('site_id', $siteId);
        }

        return [
            'reports' => $reportsQuery->latest()->get(),
            'problems' => $problemsQuery->latest()->get(),
        ];
    }

    public function getSitesWithReports(array $filters = []): mixed
    {
        $date = array_key_exists('date', $filters)
            ? $filters['date']
            : now()->toDateString();

        $query = $this->model->newQuery();

        if (! empty($filters['site_id'])) {
            $query->where('id', $filters['site_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->whereHas('reports', function ($reportQuery) use ($date, $filters) {
            if ($date !== null && $date !== 'all') {
                $reportQuery->whereDate('created_at', $date);
            }
            if (! empty($filters['report_type'])) {
                $reportQuery->where('report_type', $filters['report_type']);
            }
            if (! empty($filters['user_id'])) {
                $reportQuery->where('user_id', $filters['user_id']);
            }
        });

        $query->with([
            'location',
            'project',
            'users',
            'reports' => function ($reportQuery) use ($date, $filters) {
                if ($date !== null && $date !== 'all') {
                    $reportQuery->whereDate('created_at', $date);
                }
                if (! empty($filters['report_type'])) {
                    $reportQuery->where('report_type', $filters['report_type']);
                }
                if (! empty($filters['user_id'])) {
                    $reportQuery->where('user_id', $filters['user_id']);
                }
                $reportQuery->with(['user', 'resource.resource', 'supplier'])->latest();
            },
        ]);

        $query->latest('id');

        $paginate = ! empty($filters['paginate']) || ! empty($filters['page']);
        $limit = (int) ($filters['limit'] ?? self::LIMIT);

        return $paginate ? $query->paginate($limit) : $query->get();
    }
}
              
