<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\SiteStatusEnum;
use App\Enum\SiteReportTypeEnum;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ReportRequest;
use App\Http\Requests\Api\V1\Dashboard\SiteProblemRequest;
use App\Http\Requests\Api\V1\Dashboard\SiteRequest;
use App\Http\Requests\Api\V1\Dashboard\UpdateSiteStatusRequest;
use App\Http\Requests\Api\V1\Dashboard\UpdateSiteProblemRequest;
use App\Http\Resources\Api\V1\Dashboard\ReportResource;
use App\Http\Resources\Api\V1\Dashboard\SiteProblemResource;
use App\Http\Resources\Api\V1\Dashboard\SiteResource;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Repositories\Contracts\SiteContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SiteController extends BaseApiController
{
    private SiteContract $siteRepository;

    /**
     * SiteController constructor.
     * @param SiteContract $repository
     */
    public function __construct(SiteContract $repository)
    {
        $this->siteRepository = $repository;
        parent::__construct($repository, SiteResource::class, 'sites');
    }

    /**
     * Retrieve site reports:
     * - If no specific site and no duration is selected: returns today's used materials.
     * - If a specific site or duration is selected: returns all reports (filtered by site and/or duration).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function usedMaterials(Request $request): JsonResponse
    {
        $siteId = $request->input('site_id', $request->header('site_id', $request->input('site', $request->header('site'))));

        $from = $request->input('from_date', $request->input('from', $request->header('from_date', $request->header('from', $request->input('start_date', $request->header('start_date'))))));
        $to = $request->input('to_date', $request->input('to', $request->header('to_date', $request->header('to', $request->input('end_date', $request->header('end_date'))))));
        $date = $request->input('date', $request->input('report_date', $request->header('date', $request->header('report_date', $request->input('created_at', $request->header('created_at'))))));

        // Handle helper formats if passed in duration (e.g. 'today', 'week', 'month', 'all' or ['from' => ..., 'to' => ...])
        $durationParam = $request->input('duration', $request->header('duration'));
        if (is_array($durationParam)) {
            $from = $from ?: ($durationParam['from'] ?? $durationParam['start_date'] ?? null);
            $to = $to ?: ($durationParam['to'] ?? $durationParam['end_date'] ?? null);
        } elseif (is_string($durationParam) && str_contains($durationParam, ',')) {
            [$dFrom, $dTo] = array_pad(explode(',', $durationParam), 2, null);
            $from = $from ?: trim($dFrom);
            $to = $to ?: trim($dTo);
        } elseif ($durationParam === 'today') {
            $date = $date ?: now()->toDateString();
        } elseif ($durationParam === 'week') {
            $from = $from ?: now()->startOfWeek()->toDateString();
            $to = $to ?: now()->endOfWeek()->toDateString();
        } elseif ($durationParam === 'month') {
            $from = $from ?: now()->startOfMonth()->toDateString();
            $to = $to ?: now()->endOfMonth()->toDateString();
        }

        $hasDuration = ! empty($from) || ! empty($to) || (! empty($date) && $date !== 'all');

        $reportsQuery = SiteReport::query()
            ->with(['site', 'user', 'resource', 'supplier']);

        if (! $siteId && ! $hasDuration) {
            // Default: Neither site nor duration was specified -> get today's used materials
            $reportsQuery->whereDate('created_at', now()->toDateString());
            if ($request->filled('report_type')) {
                $reportsQuery->where('report_type', $request->input('report_type'));
            } else {
                $reportsQuery->where('report_type', SiteReportTypeEnum::MATERIALS->value);
            }
        } else {
            // Specific site or duration specified -> get all reports
            if (! empty($siteId)) {
                $reportsQuery->where('site_id', $siteId);
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

            // Optional report_type filter if provided
            if ($request->filled('report_type')) {
                $reportsQuery->where('report_type', $request->input('report_type'));
            }
        }

        $reportsQuery->latest('created_at')->latest('id');

        $reports = ($request->has('page') || $request->boolean('paginate'))
            ? $reportsQuery->paginate((int) $request->input('limit', 10))
            : $reportsQuery->get();

        $data = [
            'Reports' => ReportResource::collection($reports),
            'reports' => ReportResource::collection($reports),
            'filters' => [
                'site_id' => $siteId ?: null,
                'from' => $from ?: null,
                'to' => $to ?: null,
                'date' => $date ?: null,
                'is_default_today_materials' => (! $siteId && ! $hasDuration),
            ],
        ];

        if ($reports instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $data['pagination'] = [
                'total' => $reports->total(),
                'count' => $reports->count(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'total_pages' => $reports->lastPage(),
            ];
        } else {
            $data['total'] = $reports->count();
        }

        return $this->respondWithSuccess(__('Reports retrieved successfully'), $data);
    }

    /**
     * @param SiteRequest $request
     * @return JsonResponse
     */
    public function store(SiteRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $userIds = $attributes['user_ids'] ?? [];
        unset($attributes['user_ids']);
        $site = $this->siteRepository->createSite($attributes, $userIds);

        return $this->respondWithSuccess(__('Site added successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Site $Site
     * @return JsonResponse
     */
    public function show(Site $site, Request $request): JsonResponse
    {
        $date = Validator::make(
            ['report_date' => $request->query('report_date', $request->header('report_date'))],
            ['report_date' => ['nullable', 'date_format:Y-m-d']]
        )->validate()['report_date'] ?? null;

        $duration = Validator::make(
            [
                'report_from' => $request->query('report_from', $request->header('report_from')),
                'report_to' => $request->query('report_to', $request->header('report_to')),
            ],
            [
                'report_from' => ['nullable', 'date_format:Y-m-d'],
                'report_to' => ['nullable', 'date_format:Y-m-d'],
            ]
        )->validate();

        $details = $this->siteRepository->getSiteDetails($site, $date, $duration);
        $reportsByDate = collect($details['reports'])->groupBy(
            fn ($report) => $report->created_at->toDateString()
        );
        $problemsByDate = collect($details['problems'])->groupBy(
            fn ($problem) => $problem->created_at->toDateString()
        );
        $dates = $reportsByDate->keys()
            ->merge($problemsByDate->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $days = $dates->map(function (string $day) use ($reportsByDate, $problemsByDate) {
            $reports = $reportsByDate->get($day, collect());
            $problems = $problemsByDate->get($day, collect());

            return [
                'date' => $day,
                SiteReportTypeEnum::LABOR->value => $this->groupReportsByUploader(
                    $reports->filter(fn ($report) => $report->report_type === SiteReportTypeEnum::LABOR)->values()
                ),
                SiteReportTypeEnum::MATERIALS->value => $this->groupReportsByUploader(
                    $reports->filter(fn ($report) => $report->report_type === SiteReportTypeEnum::MATERIALS)->values()
                ),
                SiteReportTypeEnum::EQUIPMENT->value => $this->groupReportsByUploader(
                    $reports->filter(fn ($report) => $report->report_type === SiteReportTypeEnum::EQUIPMENT)->values()
                ),
                'problems' => SiteProblemResource::collection($problems->values()),
            ];
        });

        return $this->respondWithSuccess(__('Site details'), [
            'Site' => new SiteResource($site),
            'reports' => [
                'days' => $days,
            ],
        ]);
    }

    private function groupReportsByUploader(Collection $reports): array
    {
        return $reports->groupBy('user_id')
            ->map(function (Collection $uploaderReports) {
                $uploader = $uploaderReports->first()->user;

                return [
                    'user_id' => $uploader?->id,
                    'user' => $uploader ? [
                        'id' => $uploader->id,
                        'name' => $uploader->name,
                    ] : null,
                    'reports' => ReportResource::collection($uploaderReports->values()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SiteRequest $request
     * @param Site $Site
     * @return JsonResponse
     */
    public function update(SiteRequest $request, Site $site): JsonResponse
    {
        $attributes = $request->validated();
        $hasUserIds = array_key_exists('user_ids', $attributes);
        $userIds = $attributes['user_ids'] ?? [];
        unset($attributes['user_ids']);
        $site = $this->siteRepository->updateSite($site, $attributes, $hasUserIds ? $userIds : null);
        return $this->respondWithSuccess(__('Site updated successfully'), [
            'Site' => (new SiteResource($site)),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Site $Site
     *
     * @return JsonResponse
     */
    public function destroy(Site $site): JsonResponse
    {
        $this->siteRepository->deleteSite($site);

        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function durationExpand(Request $request, Site $site): JsonResponse
    {
        $validatedData = $request->validate([
            'duration_in_months' => ['required', 'integer', 'min:1'],
        ]);

        $site = $this->siteRepository->expandSiteDuration($site, $validatedData['duration_in_months']);

        return $this->respondWithSuccess(__('Site duration expanded successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }

    public function addUserToSite(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
        ]);

        $site = $this->siteRepository->syncSiteUsers($validatedData['site_id'], $validatedData['user_ids']);

        return $this->respondWithSuccess(__('Users added to site successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }


    public function removeUserFromSite(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
        ]);

        $site = $this->siteRepository->removeSiteUser($validatedData['site_id'], $validatedData['user_id']);

        return $this->respondWithSuccess(__('User removed from site successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }


    public function updateStatus(Site $site, UpdateSiteStatusRequest $request): JsonResponse
    {
        $site = $this->siteRepository->updateSiteStatus($site, $request->validated('status'));

        return $this->respondWithSuccess(__('Site status updated successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }



    public function addReportToSite(ReportRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $reports = $this->siteRepository->addSiteReports($attributes, $request->user()->id);

        $response = $reports->count() === 1
            ? ['Report' => new ReportResource($reports->first())]
            : ['Reports' => ReportResource::collection($reports)];

        return $this->respondWithSuccess(__('Report added to site successfully'), $response);
    }

     public function updateReportToSite(Site $site, ReportRequest $request): JsonResponse
    {
        $attributes = $request->all();
        $reports = $this->siteRepository->updateSiteReports($attributes, $request->user()->id);

        $response = $reports->count() === 1
            ? ['Report' => new ReportResource($reports->first())]
            : ['Reports' => ReportResource::collection($reports)];

        return $this->respondWithSuccess(__('Report updated successfully'), $response);
    }

    public function showReport(SiteReport $report): JsonResponse
    {
        return $this->respondWithSuccess(__('Site report details'), [
            'Report' => new ReportResource($this->siteRepository->getReportDetails($report)),
        ]);
    }

    public function deleteReport(SiteReport $report): JsonResponse
    {
        $this->siteRepository->deleteSiteReport($report);

        return $this->respondWithSuccess(__('Site report deleted successfully'));
    }

    public function deleteReportsByDate(Request $request , Site $site): JsonResponse
    {
        $filters = $request->validate([
            'created_at' => ['required', 'date_format:Y-m-d'],

        ]);

        $filters['site_id'] = $site->id;
        return $this->deleteReportsByFilters($filters);
    }

    public function deleteReportsByDateAndType(Request $request , Site $site): JsonResponse
    {
        $filters = $request->validate([
            'created_at' => ['required', 'date_format:Y-m-d'],
            'report_type' => ['required', Rule::enum(SiteReportTypeEnum::class)],
        ]);

        $filters['site_id'] = $site->id;
        return $this->deleteReportsByFilters($filters);
    }

    public function deleteReportsByDateTypeAndUser(Request $request , Site $site): JsonResponse
    {
        $filters = $request->validate([
            'created_at' => ['required', 'date_format:Y-m-d'],
            'report_type' => ['required', Rule::enum(SiteReportTypeEnum::class)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $filters['site_id'] = $site->id;

        return $this->deleteReportsByFilters($filters);
    }

    private function deleteReportsByFilters(array $filters ): JsonResponse
    {
        $deletedCount = $this->siteRepository->deleteSiteReportsByFilters($filters);

        return $this->respondWithSuccess(__('Site reports deleted successfully'), [
            'deleted_count' => $deletedCount,
        ]);
    }

    public function addProblemToSite(SiteProblemRequest $request, Site $site): JsonResponse
    {
        $attributes = $request->validated();
        $files = $attributes['attachments'] ?? [];
        unset($attributes['attachments']);

        $problem = $this->siteRepository->addSiteProblem($site, $attributes, $files);

        return $this->respondWithSuccess(__('Problem added to site successfully'), [
            'Problem' => new SiteProblemResource($problem),
        ]);
    }

    public function siteProblems(Site $site): JsonResponse
    {
        $problems = $this->siteRepository->getSiteProblems($site);

        return $this->respondWithSuccess(__('Site problems list'), [
            'Problems' => SiteProblemResource::collection($problems),
        ]);
    }

    public function showProblem(SiteProblem $problem): JsonResponse
    {
        return $this->respondWithSuccess(__('Site problem details'), [
            'Problem' => new SiteProblemResource($problem->loadMissing(['site', 'user', 'founder', 'assignee'])),
        ]);
    }

    public function updateSiteProblem(UpdateSiteProblemRequest $request, SiteProblem $problem): JsonResponse
    {
        $attributes = $request->validated();
        $files = $attributes['attachments'] ?? $request->file('attachments', []);
        unset($attributes['attachments']);

        if (! is_array($files)) {
            $files = [$files];
        }

        $problem = $this->siteRepository->updateSiteProblem($problem, $attributes, array_filter($files));

        return $this->respondWithSuccess(__('Site problem updated successfully'), [
            'Problem' => new SiteProblemResource($problem),
        ]);
    }

    public function updateSiteProblemStatus(UpdateSiteProblemRequest $request, SiteProblem $problem): JsonResponse
    {
        $problem = $this->siteRepository->updateSiteProblem($problem, $request->validated());

        return $this->respondWithSuccess(__('Problem status updated successfully'), [
            'Problem' => new SiteProblemResource($problem),
        ]);
    }

    public function deleteSiteProblem(Request $request, ?Site $site = null, ?SiteProblem $problem = null): JsonResponse
    {
        if ($problem === null) {
            $problemId = $request->route('problem') ?? $request->input('problem_id');
            if (! $problemId) {
                return $this->errorWrongArgs(__('Problem ID is required'));
            }
            $problem = SiteProblem::findOrFail($problemId);
        }

        if ($site !== null && (int) $problem->site_id !== (int) $site->id) {
            return $this->errorNotFound(__('Problem does not belong to this site'));
        }

        $this->siteRepository->deleteSiteProblem($problem);
  return $this->respondWithSuccess(__('Site problem deleted successfully'));
      
    }

    public function reportsAndProblemsByDate(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
        ]);

        $records = $this->siteRepository->getReportsAndProblemsByDate($attributes);

        return $this->respondWithSuccess(__('Site reports and problems for the selected date'), [
            'date' => $attributes['date'],
            'Reports' => ReportResource::collection($records['reports']),
            'Problems' => SiteProblemResource::collection($records['problems']),
        ]);
    }

    public function sitesWithReports(Request $request, ?Site $site = null): JsonResponse
    {
        $siteId = $site?->id ?? $request->input('site_id');
        $rawDate = $request->input('date', $request->input('report_date', $request->header('report_date')));

        $isAllDates = $request->boolean('all_dates') || $rawDate === 'all';
        $defaultDate = $isAllDates ? null : ($rawDate ?: now()->toDateString());

        $validated = Validator::make(
            array_merge($request->all(), [
                'date' => $defaultDate,
                'site_id' => $siteId,
            ]),
            [
                'date' => ['nullable', 'date_format:Y-m-d'],
                'site_id' => ['nullable', 'integer', 'exists:sites,id'],
                'report_type' => ['nullable', Rule::enum(SiteReportTypeEnum::class)],
                'project_id' => ['nullable', 'integer', 'exists:projects,id'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'status' => ['nullable', Rule::enum(SiteStatusEnum::class)],
            ]
        )->validate();

        $filters = array_filter($validated, fn ($val) => $val !== null && $val !== '');
        $filters['date'] = $isAllDates ? null : ($validated['date'] ?? now()->toDateString());

        if ($request->has('page') || $request->boolean('paginate')) {
            $filters['paginate'] = true;
            $filters['page'] = $request->input('page', 1);
            $filters['limit'] = $request->input('limit', 10);
        }

        $sites = $this->siteRepository->getSitesWithReports($filters);

        $data = [
            'date' => $filters['date'],
            'Sites' => SiteResource::collection($sites),
            'sites' => SiteResource::collection($sites),
        ];

        if ($sites instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $data['pagination'] = [
                'total' => $sites->total(),
                'count' => $sites->count(),
                'per_page' => $sites->perPage(),
                'current_page' => $sites->currentPage(),
                'total_pages' => $sites->lastPage(),
            ];
        } else {
            $data['total_sites'] = $sites->count();
        }

        return $this->respondWithSuccess(__('Sites with uploaded reports'), $data);
    }


    /**
     * Retrieve sites that have reports and problems for today or a specified date.
     *
     * Optionally filters by site_id and/or created_at date.
     *
     * @return JsonResponse
     */
    public function getDailySiteReports(): JsonResponse
    {
        $createdAt = request()->input('created_at', request()->input('date', now()->toDateString()));
        $siteId = request()->input('site_id');

        $reportsQuery = SiteReport::query()
            ->with(['site', 'user', 'resource', 'supplier']);

        $problemsQuery = SiteProblem::query()
            ->with(['site', 'user', 'founder', 'assignee']);

        // Filter by site_id if provided
        if (! empty($siteId)) {
            $reportsQuery->where('site_id', $siteId);
            $problemsQuery->where('site_id', $siteId);
        }

        // Filter by created_at (date) for reports or problems
        if (! empty($createdAt) && $createdAt !== 'all') {
            $reportsQuery->whereDate('created_at', $createdAt);
            $problemsQuery->whereDate('created_at', $createdAt);
        }

        $reports = $reportsQuery->latest()->get();
        $problems = $problemsQuery->latest()->get();

        // Group by day (Y-m-d)
        $reportsByDate = $reports->groupBy(fn ($report) => $report->created_at ? $report->created_at->format('Y-m-d') : null);
        $problemsByDate = $problems->groupBy(fn ($problem) => $problem->created_at ? $problem->created_at->format('Y-m-d') : null);

        $dates = $reportsByDate->keys()
            ->merge($problemsByDate->keys())
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        // If a specific date was requested and had no records, still include that date
        if ($dates->isEmpty() && ! empty($createdAt) && $createdAt !== 'all') {
            $dates = collect([$createdAt]);
        }

        $translate = function ($value) {
            if (is_array($value)) {
                return $value[app()->getLocale()] ?? $value['en'] ?? $value['ar'] ?? reset($value);
            }

            return $value;
        };

        $days = $dates->map(function (string $day) use ($reportsByDate, $problemsByDate, $translate) {
            $dayReports = $reportsByDate->get($day, collect());
            $dayProblems = $problemsByDate->get($day, collect());

            $siteIds = $dayReports->pluck('site_id')
                ->merge($dayProblems->pluck('site_id'))
                ->filter()
                ->unique()
                ->values();

            $sites = $siteIds->map(function ($currentSiteId) use ($dayReports, $dayProblems, $translate) {
                $siteReports = $dayReports->where('site_id', $currentSiteId)->values();
                $siteProblems = $dayProblems->where('site_id', $currentSiteId)->values();

                $site = $siteReports->first()?->site ?? $siteProblems->first()?->site;

                $getReportType = fn ($r) => $r->report_type instanceof SiteReportTypeEnum
                    ? $r->report_type->value
                    : (string) $r->report_type;

                return [
                    'site_id' => $currentSiteId,
                    'site_name' => $site ? $translate($site->name) : null,
                    'site' => $site ? [
                        'id' => $site->id,
                        'name' => $translate($site->name),
                    ] : null,
                    'reports' => [
                        SiteReportTypeEnum::MATERIALS->value => ReportResource::collection(
                            $siteReports->filter(fn ($r) => $getReportType($r) === SiteReportTypeEnum::MATERIALS->value)->values()
                        ),
                        SiteReportTypeEnum::LABOR->value => ReportResource::collection(
                            $siteReports->filter(fn ($r) => $getReportType($r) === SiteReportTypeEnum::LABOR->value)->values()
                        ),
                        SiteReportTypeEnum::EQUIPMENT->value => ReportResource::collection(
                            $siteReports->filter(fn ($r) => $getReportType($r) === SiteReportTypeEnum::EQUIPMENT->value)->values()
                        ),
                        SiteReportTypeEnum::ISSUES->value =>  SiteProblemResource::collection($siteProblems),
                    ],
                ];
            })->values();

            return [
                'date' => $day,
                'data' => $sites,
            ];
        })->values();

        return $this->respondWithSuccess(
            __('Sites with reports and problems for :date', ['date' => $createdAt ?: 'all']),
            [
                'days' => $days,
                //'data' => $days,
            ]
        );
    }
}
