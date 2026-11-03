<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\SiteReportTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\CombinedReportRequest;
use App\Http\Resources\Api\V1\Dashboard\CombinedReportResource;
use App\Http\Resources\Api\V1\Dashboard\ReportResource;
use App\Http\Resources\Api\V1\Dashboard\SiteProblemResource;
use App\Models\CombinedReport;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CombinedReportController extends Controller
{
    use ApiResponseTrait;

    public function store(CombinedReportRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $site = Site::findOrFail($attributes['site_id']);

        $attributes['name'] = $attributes['name']
            ?? trim($site->getTranslation('name', app()->getLocale()).' '.$attributes['from_date'].' - '.$attributes['to_date']);

        $combinedReport = CombinedReport::create($attributes);

        return $this->respondWithSuccess(__('Combined report created successfully'), [
            'CombinedReport' => new CombinedReportResource($combinedReport),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
        ]);

        return $this->combinedReportsResponse($validated['site_id'] ?? null);
    }

    public function siteIndex(Site $site): JsonResponse
    {
        return $this->combinedReportsResponse($site->id);
    }

    private function combinedReportsResponse(?int $siteId): JsonResponse
    {
        $query = CombinedReport::query()->with('site')->latest('id');

        $problemreportsQuery = SiteProblem::query();
        $reportsQuery = SiteReport::query();

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
            $problemreportsQuery->where('site_id', $siteId);
            $reportsQuery->where('site_id', $siteId);
        }

        $reports = $query->get();
        $siteProblems = $problemreportsQuery->get();
        $siteReports = $reportsQuery->get();


        $reports = $reports->map(function ($report) {
            return new CombinedReportResource($report);
        });

        return $this->respondWithSuccess(__('Combined report retrieved successfully'), [
            'CombinedReports' => CombinedReportResource::collection($reports),
            "reports_labour" => ReportResource::collection($siteReports->where('report_type', SiteReportTypeEnum::LABOR)),
            "reports_material" => ReportResource::collection($siteReports->where('report_type', SiteReportTypeEnum::MATERIALS)),
            "reports_equipment" => ReportResource::collection($siteReports->where('report_type', SiteReportTypeEnum::EQUIPMENT)),
            "siteProblems" => SiteProblemResource::collection($siteProblems),
            "counts" => [
                "reports_labour" => $siteReports->where('report_type', SiteReportTypeEnum::LABOR)->count(),
                "reports_material" => $siteReports->where('report_type', SiteReportTypeEnum::MATERIALS)->count(),
                "reports_equipment" => $siteReports->where('report_type', SiteReportTypeEnum::EQUIPMENT)->count(),
                "siteProblems" => $siteProblems->count(),
            ]
        ]); 
    }

    public function destroy(CombinedReport $combinedReport): JsonResponse
    {
        $combinedReport->delete();

        return $this->respondWithSuccess(__('Combined report deleted successfully'));
    }

    public function show(CombinedReport $combinedReport): JsonResponse
    {

        $reports = SiteReport::where("created_at", ">=", $combinedReport->from_date)
            ->where("created_at", "<=", $combinedReport->to_date)
            ->where("site_id", $combinedReport->site_id)
            ->get();
            
        $siteProblems = SiteProblem::where("created_at", ">=", $combinedReport->from_date)
            ->where("created_at", "<=", $combinedReport->to_date)
            ->where("site_id", $combinedReport->site_id)
            ->get();

        return $this->respondWithSuccess(__('Combined report retrieved successfully'), [
            'CombinedReport' => new CombinedReportResource($combinedReport->load('site')),
            "reports_labour" => ReportResource::collection($reports->where('report_type', SiteReportTypeEnum::LABOR)),
            "reports_material" => ReportResource::collection($reports->where('report_type', SiteReportTypeEnum::MATERIALS)),
            "reports_equipment" => ReportResource::collection($reports->where('report_type', SiteReportTypeEnum::EQUIPMENT)),
            "siteProblems" => SiteProblemResource::collection($siteProblems),
            "counts" => [
                "reports_labour" => $reports->where('report_type', SiteReportTypeEnum::LABOR)->count(),
                "reports_material" => $reports->where('report_type', SiteReportTypeEnum::MATERIALS)->count(),
                "reports_equipment" => $reports->where('report_type', SiteReportTypeEnum::EQUIPMENT)->count(),
                "siteProblems" => $siteProblems->count(),
            ]
        ]);
    }
}
