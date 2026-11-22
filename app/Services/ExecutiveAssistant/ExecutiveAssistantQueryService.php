<?php

namespace App\Services\ExecutiveAssistant;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Project;
use App\Models\ProjectEngineers;
use App\Models\ProjectResources;
use App\Models\Resource;
use App\Models\resourcePrice;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Models\SiteUser;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ExecutiveAssistantQueryService
{
    /**
     * Determine if a user has access to a project.
     */
    public function canAccessProject(User $user, int $projectId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return in_array($projectId, $this->getAllowedProjectIds($user), true);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(User $user): bool
    {
        return strtolower((string) $user->type) === 'admin';
    }

    /**
     * Get list of project IDs allowed for a user.
     */
    public function getAllowedProjectIds(User $user): array
    {
        if ($this->isAdmin($user)) {
            return Project::pluck('id')->toArray();
        }

        $ownedIds = Project::where('user_id', $user->id)->pluck('id')->toArray();

        $assignedIds = ProjectEngineers::where('user_id', $user->id)
            ->where('active', true)
            ->pluck('project_id')
            ->toArray();

        $siteProjectIds = Site::whereIn('id', function ($query) use ($user) {
            $query->select('site_id')
                ->from('site_user')
                ->where('user_id', $user->id);
        })->pluck('project_id')->toArray();

        return array_values(array_unique(array_merge($ownedIds, $assignedIds, $siteProjectIds)));
    }

    /**
     * Resolve project model with security check.
     */
    public function resolveProject(User $user, ?int $projectId = null, ?string $projectName = null): ?Project
    {
        $query = Project::query();

        if ($projectId) {
            $query->where('id', $projectId);
        } elseif (!empty($projectName)) {
            $query->where(function ($q) use ($projectName) {
                $q->where('name', 'LIKE', "%{$projectName}%")
                    ->orWhere('description', 'LIKE', "%{$projectName}%");
            });
        } else {
            return null;
        }

        if (!$this->isAdmin($user)) {
            $allowedIds = $this->getAllowedProjectIds($user);
            $query->whereIn('id', $allowedIds);
        }

        return $query->first();
    }

    /**
     * Tool 1: Get executive project status across the 4 execution pillars.
     */
    public function getProjectExecutiveStatus(User $user, array $args): array
    {
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;
        $siteId = isset($args['site_id']) ? (int) $args['site_id'] : null;
        $dateFilter = $args['date'] ?? null;

        $project = $this->resolveProject($user, $projectId, $projectName);

        if (!$project && ($projectId || $projectName)) {
            return [
                'found' => false,
                'message' => 'لم يتم العثور على مشروع بهذا الاسم/المعرف، أو ليس لديك صلاحية للاطلاع عليه.',
            ];
        }

        // If no specific project was provided, list user's projects overview
        if (!$project) {
            $allowedIds = $this->getAllowedProjectIds($user);
            $projectsList = Project::whereIn('id', $allowedIds)
                ->select(['id', 'name', 'status', 'end_date'])
                ->limit(10)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status,
                    'end_date' => $p->end_date ? Carbon::parse($p->end_date)->toDateString() : null,
                ]);

            return [
                'found' => true,
                'is_summary' => true,
                'total_projects_accessible' => count($allowedIds),
                'projects' => $projectsList,
            ];
        }

        // Project sites
        $sitesQuery = Site::where('project_id', $project->id);
        if ($siteId) {
            $sitesQuery->where('id', $siteId);
        }
        $sites = $sitesQuery->get();
        $siteIds = $sites->pluck('id')->toArray();

        // 4 Execution Pillars from Site Reports
        $reportsQuery = SiteReport::whereIn('site_id', $siteIds);
        if (!empty($dateFilter)) {
            $reportsQuery->whereDate('created_at', $dateFilter);
        }

        $reports = $reportsQuery->with(['resource', 'supplier'])->get();

        // Pillar 1: Materials
        $materialsReports = $reports->where('report_type.value', 'materials')
            ->concat($reports->where('report_type', 'materials'));
        $materialsList = $materialsReports->take(5)->map(fn ($r) => [
            'material' => $r->resource?->name ?? 'مادة غير محددة',
            'quantity' => $r->quantity,
            'supplier' => $r->supplier?->name ?? 'غير محدد',
            'notes' => $r->notes,
        ])->values()->all();

        // Total allocated materials in ProjectResources
        $projectResourcesCount = ProjectResources::where('project_id', $project->id)->count();

        // Pillar 2: Equipment
        $equipmentReports = $reports->where('report_type.value', 'equipment')
            ->concat($reports->where('report_type', 'equipment'));
        $equipmentList = $equipmentReports->take(5)->map(fn ($r) => [
            'equipment_info' => $r->report_content ?? $r->notes,
            'quantity' => $r->quantity,
        ])->values()->all();

        // Pillar 3: Labor
        $laborReports = $reports->where('report_type.value', 'labor')
            ->concat($reports->where('report_type', 'labor'));
        $laborCount = $laborReports->count();
        $laborList = $laborReports->take(5)->map(fn ($r) => [
            'craft_or_details' => $r->report_content ?? $r->notes,
            'labor_count' => $r->quantity,
        ])->values()->all();

        // Pillar 4: Subcontractors & Issues (Site Problems)
        $problemReports = $reports->where('report_type.value', 'issues')
            ->concat($reports->where('report_type', 'issues'));

        $problems = SiteProblem::whereIn('site_id', $siteIds)
            ->whereIn('status', ['open', 'in_progress', 'pending', 'new'])
            ->with(['assignee'])
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'priority' => $p->priority ?? 'medium',
                'status' => $p->status,
                'explanation' => $p->explanation,
                'causes' => $p->causes,
                'assigned_to' => $p->assignee?->name ?? 'غير مسند',
            ])->all();

        // Milestone and timeline check
        $now = Carbon::now();
        $isOverdue = false;
        $delayDays = 0;
        if ($project->end_date) {
            $endDate = Carbon::parse($project->end_date);
            if ($endDate->isPast() && !in_array(strtolower((string)$project->status), ['completed', 'finished'])) {
                $isOverdue = true;
                $delayDays = (int) $endDate->diffInDays($now);
            }
        }

        return [
            'found' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'end_date' => $project->end_date ? Carbon::parse($project->end_date)->toDateString() : null,
                'is_overdue' => $isOverdue,
                'delay_days' => $delayDays,
                'total_cost' => $project->total_price ?? 0,
            ],
            'sites_count' => $sites->count(),
            'pillars' => [
                'materials' => [
                    'reports_count' => $materialsReports->count(),
                    'allocated_materials_count' => $projectResourcesCount,
                    'recent_items' => $materialsList,
                ],
                'equipment' => [
                    'reports_count' => $equipmentReports->count(),
                    'recent_items' => $equipmentList,
                ],
                'labor' => [
                    'reports_count' => $laborCount,
                    'recent_items' => $laborList,
                ],
                'subcontractors_and_issues' => [
                    'issue_reports_count' => $problemReports->count(),
                    'open_problems_count' => count($problems),
                    'active_problems' => $problems,
                ],
            ],
        ];
    }

    /**
     * Tool 2: Check material quantities and stock levels.
     */
    public function checkMaterialStockAndQuantities(User $user, array $args): array
    {
        $materialName = $args['material_name'] ?? null;
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;

        $project = null;
        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
        }

        $resourcesQuery = Resource::query();
        if (!empty($materialName)) {
            $resourcesQuery->where(function ($q) use ($materialName) {
                $q->where('name->ar', 'LIKE', "%{$materialName}%")
                    ->orWhere('name->en', 'LIKE', "%{$materialName}%")
                    ->orWhere('code', 'LIKE', "%{$materialName}%");
            });
        }

        $resources = $resourcesQuery->with(['unit', 'category'])->take(10)->get();

        if ($resources->isEmpty()) {
            return [
                'found' => false,
                'message' => "لم يتم العثور على خامات أو مواد تطابق '{$materialName}' في النظام.",
            ];
        }

        $results = [];
        foreach ($resources as $resource) {
            $resourceData = [
                'id' => $resource->id,
                'name' => $resource->name,
                'code' => $resource->code,
                'unit' => $resource->unit?->name ?? 'وحدة',
                'category' => $resource->category?->name ?? 'عام',
            ];

            // If project is specified, get project allocations
            if ($project) {
                $allocation = ProjectResources::where('project_id', $project->id)
                    ->where('resource_id', $resource->id)
                    ->first();

                $resourceData['project_allocation'] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'allocated_quantity' => $allocation?->quantity ?? 0,
                    'unit_price' => $allocation?->price ?? 0,
                    'price_after_tax' => $allocation?->price_after_tax ?? 0,
                    'section' => $allocation?->section_name ?? 'غير محدد',
                ];

                // Order items for this resource in this project's sites
                $siteIds = Site::where('project_id', $project->id)->pluck('id')->toArray();
                $orderItems = OrderItems::where('resource_id', $resource->id)
                    ->whereIn('site_id', $siteIds)
                    ->get();

                $resourceData['orders_summary'] = [
                    'approved_quantity' => $orderItems->where('status.value', 'approved')->sum('quantity'),
                    'unapproved_quantity' => $orderItems->where('status.value', 'unapproved')->sum('quantity'),
                    'rejected_quantity' => $orderItems->where('status.value', 'rejected')->sum('quantity'),
                    'returned_quantity' => $orderItems->where('status.value', 'returned_for_revision')->sum('quantity'),
                ];
            } else {
                // Latest supplier price
                $latestPrice = resourcePrice::where('resource_id', $resource->id)
                    ->orderByDesc('effective_date')
                    ->first();

                $resourceData['latest_price'] = [
                    'price' => $latestPrice?->price ?? 0,
                    'tax' => $latestPrice?->tax ?? 0,
                    'effective_date' => $latestPrice?->effective_date,
                ];
            }

            $results[] = $resourceData;
        }

        return [
            'found' => true,
            'materials_count' => count($results),
            'materials' => $results,
        ];
    }

    /**
     * Tool 3: Query subcontractor performance, issues, and milestone delays.
     */
    public function querySubcontractorAndMilestoneDelays(User $user, array $args): array
    {
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;
        $supplierName = $args['supplier_name'] ?? null;

        $data = [];

        // Project milestone & delay analysis
        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
            if ($project) {
                $now = Carbon::now();
                $isOverdue = false;
                $delayDays = 0;
                $daysRemaining = 0;

                if ($project->end_date) {
                    $endDate = Carbon::parse($project->end_date);
                    if ($endDate->isPast()) {
                        $isOverdue = true;
                        $delayDays = (int) $endDate->diffInDays($now);
                    } else {
                        $daysRemaining = (int) $now->diffInDays($endDate);
                    }
                }

                $sites = Site::where('project_id', $project->id)->get();
                $siteIds = $sites->pluck('id')->toArray();

                $activeProblems = SiteProblem::whereIn('site_id', $siteIds)
                    ->whereIn('status', ['open', 'in_progress', 'pending'])
                    ->take(5)
                    ->get()
                    ->map(fn ($p) => [
                        'priority' => $p->priority,
                        'explanation' => $p->explanation,
                        'causes' => $p->causes,
                        'suggested_solutions' => $p->suggested_solutions,
                    ]);

                $data['project_milestone'] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $project->status,
                    'end_date' => $project->end_date,
                    'is_overdue' => $isOverdue,
                    'delay_days' => $delayDays,
                    'days_remaining' => $daysRemaining,
                    'active_site_problems_count' => $activeProblems->count(),
                    'critical_problems' => $activeProblems,
                ];
            }
        }

        // Subcontractors / Suppliers
        if (!empty($supplierName)) {
            $suppliers = Supplier::where('name->ar', 'LIKE', "%{$supplierName}%")
                ->orWhere('name->en', 'LIKE', "%{$supplierName}%")
                ->take(5)
                ->get();

            $suppliersData = [];
            foreach ($suppliers as $supplier) {
                $reportCount = SiteReport::where('supplier_id', $supplier->id)->count();

                $suppliersData[] = [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'contact' => $supplier->contact,
                    'status' => $supplier->status,
                    'associated_reports_count' => $reportCount,
                ];
            }

            $data['subcontractors'] = $suppliersData;
        }

        if (empty($data)) {
            return [
                'found' => false,
                'message' => 'لم يتم العثور على بيانات للتأخير أو المقاول المحدد، يرجى التحقق من اسم المشروع أو المقاول.',
            ];
        }

        return [
            'found' => true,
            'delay_and_contractor_status' => $data,
        ];
    }

    /**
     * Tool 4: Get orders and approvals status.
     */
    public function getOrdersAndApprovalsStatus(User $user, array $args): array
    {
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $orderNumber = $args['order_number'] ?? null;
        $status = $args['status'] ?? null;

        $itemsQuery = OrderItems::with(['order', 'resource', 'site']);

        if ($projectId) {
            $siteIds = Site::where('project_id', $projectId)->pluck('id')->toArray();
            $itemsQuery->whereIn('site_id', $siteIds);
        }

        if (!empty($orderNumber)) {
            $itemsQuery->whereHas('order', function ($q) use ($orderNumber) {
                $q->where('order_number', 'LIKE', "%{$orderNumber}%");
            });
        }

        if (!empty($status)) {
            $itemsQuery->where('status', $status);
        }

        $items = $itemsQuery->orderByDesc('created_at')->take(15)->get();

        $statusCounts = [
            'unapproved' => OrderItems::where('status', 'unapproved')->count(),
            'approved' => OrderItems::where('status', 'approved')->count(),
            'rejected' => OrderItems::where('status', 'rejected')->count(),
            'returned_for_revision' => OrderItems::where('status', 'returned_for_revision')->count(),
        ];

        $criticalItems = $items->filter(fn ($item) => in_array($item->status?->value ?? $item->status, ['unapproved', 'rejected', 'returned_for_revision']))
            ->map(fn ($item) => [
                'order_number' => $item->order?->order_number,
                'resource' => $item->resource?->name ?? 'خامة غير محددة',
                'quantity' => $item->quantity,
                'status' => $item->status?->value ?? $item->status,
                'priority' => $item->priority?->value ?? $item->priority,
                'rejection_reason' => $item->rejection_reason,
                'return_reason' => $item->return_reason,
                'delivery_date' => $item->delivery_date,
            ])->values()->all();

        return [
            'found' => true,
            'pipeline_counts' => $statusCounts,
            'critical_pending_or_rejected_items' => $criticalItems,
        ];
    }
}
