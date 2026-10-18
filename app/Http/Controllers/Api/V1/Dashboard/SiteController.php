<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\SiteStatusEnum;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Dashboard\ReportRequest;
use App\Http\Requests\Api\V1\Dashboard\SiteProblemRequest;
use App\Http\Requests\Api\V1\Dashboard\SiteRequest;
use App\Http\Requests\Api\V1\Dashboard\UpdateSiteProblemRequest;
use App\Http\Resources\Api\V1\Dashboard\ReportResource;
use App\Http\Resources\Api\V1\Dashboard\SiteProblemResource;
use App\Http\Resources\Api\V1\Dashboard\SiteResource;
use App\Models\Resource;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Models\User;
use App\Repositories\Contracts\SiteContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;

class SiteController extends BaseApiController
{
    /**
     * SiteController constructor.
     * @param SiteContract $repository
     */
    public function __construct(SiteContract $repository)
    {
        parent::__construct($repository, SiteResource::class, 'sites');
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

        $site = DB::transaction(function () use ($attributes, $userIds) {

        if($userIds === []){
            $status = SiteStatusEnum::WAIT_ASSIGN
->value;
            $attributes['status'] = $status;
        }else{
            
            $status = SiteStatusEnum::IN_PROGRESS->value; 
            $attributes['status'] = $status;
        }
            $site = $this->repository->create($attributes);
            $site->users()->sync($userIds);

            return $site;
        });

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
    public function show(Site $site): JsonResponse
    {
        return $this->respondWithSuccess(__('Site details'), [
            
            'Site' => (new SiteResource($site)),
            
            'reports' => ReportResource::collection($site->reports()->with(['user', 'resource', 'supplier'])->latest()->get()),
            'problems' => SiteProblemResource::collection($site->problems()->with(['user', 'founder', 'assignee'])->latest()->get()),
        ]);
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

        DB::transaction(function () use (&$site, $attributes, $hasUserIds, $userIds) {
            if ($attributes !== []) {
                $site = $this->repository->update($site, $attributes);
            }

            if ($hasUserIds) {
                $site->users()->sync($userIds);
            }
        });

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
        $attachmentPaths = DB::transaction(function () use ($site) {
            $attachmentPaths = $site->problems()
                ->get()
                ->flatMap(fn (SiteProblem $problem) => collect($problem->attachments ?? [])->pluck('path'))
                ->filter()
                ->values()
                ->all();

            $site->users()->detach();
            $site->reports()->delete();
            $site->problems()->delete();
            $this->repository->remove($site);

            return $attachmentPaths;
        });

        if ($attachmentPaths !== [] && ! Storage::disk('public')->delete($attachmentPaths)) {
            throw new RuntimeException('Unable to delete site problem attachments.');
        }

        return $this->respondWithSuccess(trans('messages.responses.deleted'));
    }


    public function durationExpand(Request $request, Site $site): JsonResponse
    {
        $validatedData = $request->validate([
            'duration_in_months' => ['required', 'integer', 'min:1'],
        ]);

        if($validatedData['duration_in_months'] <= $site->duration_in_months){
            return $this->respondWithError(__('The new duration must be greater than the current duration.'));
        }


        if ($site->status === SiteStatusEnum::FINISHED->value || $site->status === SiteStatusEnum::DEADLINE_PASSED->value) {
            $site->status = SiteStatusEnum::IN_PROGRESS->value;
        }

        $site->update($validatedData);

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

        $site = Site::findOrFail($validatedData['site_id']);
        $site = DB::transaction(function () use ($site, $validatedData) {
            $site->users()->sync($validatedData['user_ids']);

            return $site->load('users');
        });

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

        $user = User::findOrFail($validatedData['user_id']);
        $site = Site::findOrFail($validatedData['site_id']);

        DB::transaction(function () use ($site, $user) {
            $site->users()->detach($user->id);
        });

        return $this->respondWithSuccess(__('User removed from site successfully'), [
            'Site' => new SiteResource($site->fresh('users')),
        ]);
    }


    public function updateStatus(Site $site, Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'status' => ['required', new Enum(SiteStatusEnum::class)],
        ]);

        $site->update($validatedData);

        return $this->respondWithSuccess(__('Site status updated successfully'), [
            'Site' => new SiteResource($site),
        ]);
    }



    public function addReportToSite(ReportRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $siteId = $attributes['site_id'];

        $reportItems = $attributes['resources'] ?? [$attributes];
        unset($attributes['resources']);

        $site = Site::findOrFail($siteId);
        $userId = $request->user()->id;

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

        $reportData = array_map(function (array $item) use ($attributes, $latestPrices, $userId) {
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

        $reports = DB::transaction(
            fn () => $site->reports()->createMany($reportData)
        );

        $response = $reports->count() === 1
            ? ['Report' => new ReportResource($reports->first())]
            : ['Reports' => ReportResource::collection($reports)];

        return $this->respondWithSuccess(__('Report added to site successfully'), $response);
    }

    public function addProblemToSite(SiteProblemRequest $request, Site $site): JsonResponse
    {
        $attributes = $request->validated();
        $files = $attributes['attachments'] ?? [];
        unset($attributes['attachments']);

        $problem = DB::transaction(function () use ($site, $attributes, $files) {
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

        return $this->respondWithSuccess(__('Problem added to site successfully'), [
            'Problem' => new SiteProblemResource($problem),
        ]);
    }

    public function siteProblems(Site $site): JsonResponse
    {
        $problems = $site->problems()
            ->with(['site', 'user', 'founder'])
            ->latest()
            ->get();

        return $this->respondWithSuccess(__('Site problems list'), [
            'Problems' => SiteProblemResource::collection($problems),
        ]);
    }

    public function updateSiteProblemStatus(UpdateSiteProblemRequest $request, SiteProblem $problem): JsonResponse
    {
        $problem->update($request->validated());

        return $this->respondWithSuccess(__('Problem status updated successfully'), [
            'Problem' => new SiteProblemResource($problem->fresh(['site', 'user', 'founder', 'assignee'])),
        ]);
    }

    public function reportsAndProblemsByDate(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reports = SiteReport::query()
            ->with(['site', 'user', 'resource', 'supplier'])
            ->whereDate('created_at', $attributes['date'])
            ->latest()
            ->get();
        $problems = SiteProblem::query()
            ->with(['site', 'user', 'founder'])
            ->whereDate('created_at', $attributes['date'])
            ->latest()
            ->get();

        return $this->respondWithSuccess(__('Site reports and problems for the selected date'), [
            'date' => $attributes['date'],
            'Reports' => ReportResource::collection($reports),
            'Problems' => SiteProblemResource::collection($problems),
        ]);
    }
}
