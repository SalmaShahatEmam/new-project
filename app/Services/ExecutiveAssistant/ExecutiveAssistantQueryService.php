<?php

namespace App\Services\ExecutiveAssistant;

use App\Models\CombinedReport;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
     * Tool: Get daily field reports (site reports) for a given date.
     * This is the primary tool for "تقارير اليوم" / "الموقف اليومي" queries.
     */
    public function getDailyFieldReports(User $user, array $args = []): array
    {
        $date        = $args['date']        ?? now()->toDateString();
        $projectId   = isset($args['project_id'])   ? (int) $args['project_id']   : null;
        $projectName = $args['project_name'] ?? null;
        $siteId      = isset($args['site_id'])      ? (int) $args['site_id']      : null;

        // Determine accessible site IDs
        $sitesQuery = Site::query();

        if (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $sitesQuery->whereIn('project_id', $allowedProjectIds);
        }

        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
            if ($project) {
                $sitesQuery->where('project_id', $project->id);
            }
        }

        if ($siteId) {
            $sitesQuery->where('id', $siteId);
        }

        $siteIds = $sitesQuery->pluck('id')->toArray();

        if (empty($siteIds)) {
            return [
                'found'   => false,
                'date'    => $date,
                'message' => 'لا توجد مواقع ميدانية مرتبطة بحسابك أو بالمشروع المحدد.',
            ];
        }

        // Fetch site reports for the given date
        $reportsQuery = SiteReport::whereIn('site_id', $siteIds)
            ->whereDate('created_at', $date)
            ->with(['resource', 'supplier', 'site.project']);

        $reports = $reportsQuery->get();

        if ($reports->isEmpty()) {
            return [
                'found'          => false,
                'date'           => $date,
                'reports_count'  => 0,
                'message'        => "لا توجد تقارير ميدانية مسجلة بتاريخ {$date}.",
            ];
        }

        // Group by report type
        $grouped = $reports->groupBy(fn ($r) => ($r->report_type->value ?? $r->report_type ?? 'other'));

        $summary = [];
        foreach ($grouped as $type => $typeReports) {
            $summary[$type] = $typeReports->map(fn ($r) => [
                'site'          => $r->site?->name ?? 'موقع غير محدد',
                'project'       => $r->site?->project?->name ?? 'مشروع غير محدد',
                'material'      => $r->resource?->name,
                'supplier'      => $r->supplier?->name,
                'quantity'      => $r->quantity,
                'notes'         => $r->notes,
                'report_content'=> $r->report_content,
            ])->values()->all();
        }

        // Open problems for the same sites on this date
        $openProblems = SiteProblem::whereIn('site_id', $siteIds)
            ->whereIn('status', ['open', 'in_progress', 'pending', 'new'])
            ->whereDate('created_at', '<=', $date)
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'priority'    => $p->priority,
                'status'      => $p->status,
                'explanation' => $p->explanation,
                'causes'      => $p->causes,
            ])->values()->all();

        return [
            'found'               => true,
            'date'                => $date,
            'reports_count'       => $reports->count(),
            'report_types_summary'=> $summary,
            'open_problems_count' => count($openProblems),
            'open_problems'       => $openProblems,
        ];
    }

    /**
     * Tool: Query, count, and list projects with rich executive details.
     */
    public function countProjects(User $user, array $args = []): array
    {
        $query = Project::query();

        if (!$this->isAdmin($user)) {
            $allowedIds = $this->getAllowedProjectIds($user);
            $query->whereIn('id', $allowedIds);
        }

        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $totalCount = (clone $query)->count();

        // Retrieve projects with rich details (engineers, dates, sites, cost)
        $projects = $query->with(['users' => function ($q) {
                $q->distinct()->with('engineer');
            }])
            ->orderByDesc('created_at')
            ->take(15)
            ->get()
            ->map(function (Project $p) {
                // distinct by engineer name to prevent duplicates
                $engineers = $p->users
                    ->map(fn ($ue) => [
                        'name' => $ue->engineer?->name ?? 'غير محدد',
                        'type' => $ue->type ?? 'مهندس موقع',
                    ])
                    ->unique('name')
                    ->values()
                    ->all();

                $sitesCount = Site::where('project_id', $p->id)->count();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status,
                    'description' => $p->description,
                    'start_date' => $p->created_at ? $p->created_at->toDateString() : null,
                    'end_date' => $p->end_date ? Carbon::parse($p->end_date)->toDateString() : null,
                    'engineers' => $engineers,
                    'sites_count' => $sitesCount,
                    'total_cost' => $p->total_price ?? 0,
                ];
            })->all();

        return [
            'total_count' => $totalCount,
            'projects_count' => count($projects),
            'projects' => $projects,
        ];
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
                ->with(['users.engineer'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status,
                    'description' => $p->description,
                    'start_date' => $p->created_at ? $p->created_at->toDateString() : null,
                    'end_date' => $p->end_date ? Carbon::parse($p->end_date)->toDateString() : null,
                    'engineers' => $p->users->map(fn ($ue) => [
                        'name' => $ue->engineer?->name ?? 'غير محدد',
                        'type' => $ue->type ?? 'مهندس موقع',
                    ])->values()->all(),
                    'total_cost' => $p->total_price ?? 0,
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
                'description' => $project->description,
                'start_date' => $project->created_at ? $project->created_at->toDateString() : null,
                'end_date' => $project->end_date ? Carbon::parse($project->end_date)->toDateString() : null,
                'is_overdue' => $isOverdue,
                'delay_days' => $delayDays,
                'total_cost' => $project->total_price ?? 0,
                'engineers' => $project->users->loadMissing('engineer')->map(fn ($ue) => [
                    'name' => $ue->engineer?->name ?? 'غير محدد',
                    'type' => $ue->type ?? 'مهندس موقع',
                ])->values()->all(),
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
                    ->orWhere('resource_code', 'LIKE', "%{$materialName}%");
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
                'code' => $resource->resource_code ?? $resource->code ?? null,
                'resource_code' => $resource->resource_code ?? $resource->code ?? null,
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
                    'tax' => $latestPrice?->tax_value ?? $latestPrice?->tax ?? 0,
                    'currency' => $latestPrice?->currency ?? 'SAR',
                    'effective_date' => $latestPrice?->effective_date,
                ];

                // Related project allocations for this material
                $allocations = ProjectResources::where('resource_id', $resource->id)
                    ->with('project')
                    ->take(5)
                    ->get();

                $resourceData['allocated_in_projects'] = $allocations->map(fn ($alloc) => [
                    'project_id' => $alloc->project_id,
                    'project_name' => $alloc->project?->name ?? 'غير محدد',
                    'quantity' => $alloc->quantity,
                    'section' => $alloc->section_name ?? 'عام',
                ])->values()->all();
                $resourceData['total_allocated_quantity'] = $allocations->sum('quantity');
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

    /**
     * Tool 6: Query employees, engineers, roles, job specialties, and assigned projects/sites.
     */
    public function getEmployeesAndStaffDetails(User $user, array $args): array
    {
        $employeeName = $args['employee_name'] ?? null;
        $roleOrType = $args['role_or_type'] ?? null;
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;

        $project = null;
        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
        }

        $query = User::query();
        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            $query->with('roles');
        }

        if (!empty($employeeName)) {
            $query->where(function ($q) use ($employeeName) {
                $q->where('name', 'LIKE', "%{$employeeName}%")
                    ->orWhere('email', 'LIKE', "%{$employeeName}%")
                    ->orWhere('mobile', 'LIKE', "%{$employeeName}%");
            });
        }

        if (!empty($roleOrType)) {
            $query->where(function ($q) use ($roleOrType) {
                $q->where('type', 'LIKE', "%{$roleOrType}%");
                if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                    $q->orWhereHas('roles', function ($rq) use ($roleOrType) {
                        $rq->where('name', 'LIKE', "%{$roleOrType}%");
                    });
                }
            });
        }

        if ($project) {
            $engineerIds = ProjectEngineers::where('project_id', $project->id)
                ->pluck('engineer_id')
                ->filter()
                ->toArray();

            if ($project->user_id) {
                $engineerIds[] = $project->user_id;
            }

            $query->whereIn('id', array_unique($engineerIds));
        } elseif (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $sharedEngineerIds = ProjectEngineers::whereIn('project_id', $allowedProjectIds)
                ->pluck('engineer_id')
                ->filter()
                ->toArray();
            $sharedEngineerIds[] = $user->id;
            $query->whereIn('id', array_unique($sharedEngineerIds));
        }

        $employees = $query->take(20)->get()->map(function (User $emp) {
            $projectAssignments = ProjectEngineers::where('engineer_id', $emp->id)
                ->with('project')
                ->get()
                ->map(fn ($pe) => [
                    'project_id' => $pe->project_id,
                    'project_name' => $pe->project?->name ?? 'غير محدد',
                    'specialty_type' => $pe->type ?? 'مهندس موقع',
                    'is_active' => (bool) $pe->active,
                ])->values()->all();

            $sitesAssigned = $emp->sites()->get()->map(fn ($s) => [
                'site_id' => $s->id,
                'site_name' => $s->name,
            ])->values()->all();

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'email' => $emp->email,
                'mobile' => $emp->mobile ?? 'غير مسجل',
                'system_type' => $emp->type,
                'roles' => $emp->relationLoaded('roles') ? $emp->roles->pluck('name')->toArray() : [],
                'is_active' => (bool) ($emp->is_active ?? true),
                'assigned_projects_count' => count($projectAssignments),
                'assigned_projects' => $projectAssignments,
                'assigned_sites_count' => count($sitesAssigned),
                'assigned_sites' => $sitesAssigned,
            ];
        })->all();

        return [
            'found' => count($employees) > 0,
            'employees_count' => count($employees),
            'employees' => $employees,
        ];
    }

    /**
     * Tool 7: Query sites and locations with engineers, status, and problem statistics.
     */
    public function getSitesAndLocationsSummary(User $user, array $args): array
    {
        $siteName = $args['site_name'] ?? null;
        $siteId = isset($args['site_id']) ? (int) $args['site_id'] : null;
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;

        $project = null;
        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
        }

        $query = Site::query()->with(['project', 'location', 'users']);

        if (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $query->whereIn('project_id', $allowedProjectIds);
        }

        if ($project) {
            $query->where('project_id', $project->id);
        }

        if ($siteId) {
            $query->where('id', $siteId);
        } elseif (!empty($siteName)) {
            $query->where(function ($q) use ($siteName) {
                $q->where('name->ar', 'LIKE', "%{$siteName}%")
                    ->orWhere('name->en', 'LIKE', "%{$siteName}%");
            });
        }

        $sites = $query->take(15)->get()->map(function (Site $site) {
            $engineers = $site->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'mobile' => $u->mobile,
            ])->values()->all();

            $openProblems = SiteProblem::where('site_id', $site->id)
                ->whereIn('status', ['open', 'in_progress', 'pending', 'new'])
                ->take(3)
                ->get()
                ->map(fn ($pr) => [
                    'priority' => $pr->priority,
                    'status' => $pr->status,
                    'explanation' => $pr->explanation,
                ])->values()->all();

            $reportsTotal = SiteReport::where('site_id', $site->id)->count();

            return [
                'id' => $site->id,
                'name' => $site->name,
                'status' => $site->status ?? 'active',
                'duration_in_months' => $site->duration_in_months,
                'contract_date' => $site->contract_date ? Carbon::parse($site->contract_date)->toDateString() : null,
                'project' => [
                    'id' => $site->project?->id,
                    'name' => $site->project?->name ?? 'غير محدد',
                ],
                'location' => $site->location?->name ?? 'غير محدد',
                'assigned_engineers_count' => count($engineers),
                'assigned_engineers' => $engineers,
                'statistics' => [
                    'total_reports' => $reportsTotal,
                    'materials_reports' => $site->site_report_material,
                    'equipment_reports' => $site->site_equipment,
                    'labor_reports' => $site->site_report_labour,
                    'open_problems_count' => count($openProblems),
                ],
                'recent_open_problems' => $openProblems,
            ];
        })->all();

        return [
            'found' => count($sites) > 0,
            'sites_count' => count($sites),
            'sites' => $sites,
        ];
    }

    /**
     * Tool 8: Query combined reports and aggregated metrics over dates and sites.
     */
    public function getCombinedAndAggregatedReports(User $user, array $args): array
    {
        $siteId = isset($args['site_id']) ? (int) $args['site_id'] : null;
        $siteName = $args['site_name'] ?? null;
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;
        $projectName = $args['project_name'] ?? null;
        $fromDate = $args['from_date'] ?? null;
        $toDate = $args['to_date'] ?? null;

        $sitesQuery = Site::query();
        if (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $sitesQuery->whereIn('project_id', $allowedProjectIds);
        }

        if ($projectId || $projectName) {
            $project = $this->resolveProject($user, $projectId, $projectName);
            if ($project) {
                $sitesQuery->where('project_id', $project->id);
            }
        }

        if ($siteId) {
            $sitesQuery->where('id', $siteId);
        } elseif (!empty($siteName)) {
            $sitesQuery->where(function ($q) use ($siteName) {
                $q->where('name->ar', 'LIKE', "%{$siteName}%")
                    ->orWhere('name->en', 'LIKE', "%{$siteName}%");
            });
        }

        $siteIds = $sitesQuery->pluck('id')->toArray();

        // 1. CombinedReport records
        $combinedReportsQuery = CombinedReport::whereIn('site_id', $siteIds)->with('site');
        if (!empty($fromDate)) {
            $combinedReportsQuery->whereDate('from_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $combinedReportsQuery->whereDate('to_date', '<=', $toDate);
        }
        $combinedReports = $combinedReportsQuery->take(10)->get()->map(fn ($cr) => [
            'id' => $cr->id,
            'name' => $cr->name,
            'site_name' => $cr->site?->name ?? 'غير محدد',
            'from_date' => $cr->from_date ? Carbon::parse($cr->from_date)->toDateString() : null,
            'to_date' => $cr->to_date ? Carbon::parse($cr->to_date)->toDateString() : null,
        ])->values()->all();

        // 2. Aggregated SiteReports over the period
        $reportsQuery = SiteReport::whereIn('site_id', $siteIds)->with(['resource', 'supplier']);
        if (!empty($fromDate)) {
            $reportsQuery->whereDate('created_at', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $reportsQuery->whereDate('created_at', '<=', $toDate);
        }
        $siteReports = $reportsQuery->get();

        $materialsList = $siteReports->where('report_type.value', 'materials')
            ->concat($siteReports->where('report_type', 'materials'))
            ->map(fn ($r) => [
                'material' => $r->resource?->name ?? 'مادة غير محددة',
                'quantity' => $r->quantity,
                'supplier' => $r->supplier?->name ?? 'غير محدد',
            ])->values()->all();

        $equipmentList = $siteReports->where('report_type.value', 'equipment')
            ->concat($siteReports->where('report_type', 'equipment'))
            ->map(fn ($r) => [
                'equipment' => $r->report_content ?? $r->notes,
                'quantity' => $r->quantity,
            ])->values()->all();

        $laborTotal = $siteReports->where('report_type.value', 'labor')
            ->concat($siteReports->where('report_type', 'labor'))
            ->sum('quantity');

        $problemsCount = SiteProblem::whereIn('site_id', $siteIds);
        if (!empty($fromDate)) {
            $problemsCount->whereDate('created_at', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $problemsCount->whereDate('created_at', '<=', $toDate);
        }

        return [
            'found' => count($combinedReports) > 0 || $siteReports->count() > 0,
            'date_filter' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'combined_reports_saved_count' => count($combinedReports),
            'combined_reports' => $combinedReports,
            'aggregated_period_metrics' => [
                'total_daily_reports_count' => $siteReports->count(),
                'materials_delivered_count' => count($materialsList),
                'materials_summary' => array_slice($materialsList, 0, 5),
                'equipment_deployed_count' => count($equipmentList),
                'equipment_summary' => array_slice($equipmentList, 0, 5),
                'labor_recorded_total_count' => $laborTotal,
                'problems_logged_count' => $problemsCount->count(),
            ],
        ];
    }

    /**
     * Tool 9: Query suppliers, subcontractors, contacts, pricing, and active work.
     */
    public function getSuppliersAndProcurementDetails(User $user, array $args): array
    {
        $supplierName = $args['supplier_name'] ?? null;
        $materialName = $args['material_name'] ?? null;

        $query = Supplier::query()->with(['resourcePrices.resource', 'location']);

        if (!empty($supplierName)) {
            $query->where(function ($q) use ($supplierName) {
                $q->where('name->ar', 'LIKE', "%{$supplierName}%")
                    ->orWhere('name->en', 'LIKE', "%{$supplierName}%");
            });
        }

        if (!empty($materialName)) {
            $query->whereHas('resourcePrices.resource', function ($rq) use ($materialName) {
                $rq->where('name->ar', 'LIKE', "%{$materialName}%")
                    ->orWhere('name->en', 'LIKE', "%{$materialName}%")
                    ->orWhere('resource_code', 'LIKE', "%{$materialName}%");
            });
        }

        $suppliers = $query->take(15)->get()->map(function (Supplier $sup) {
            $prices = $sup->resourcePrices->map(fn ($p) => [
                'material_name' => $p->resource?->name ?? 'مادة غير محددة',
                'resource_code' => $p->resource?->resource_code ?? null,
                'price' => $p->price,
                'tax_value' => $p->tax_value,
                'price_after_tax' => $p->price_after_tax,
                'currency' => $p->currency,
                'effective_date' => $p->effective_date,
            ])->values()->all();

            $reportsCount = SiteReport::where('supplier_id', $sup->id)->count();

            return [
                'id' => $sup->id,
                'name' => $sup->name,
                'contact' => $sup->contact,
                'email' => $sup->email,
                'status' => $sup->status,
                'location' => $sup->location?->name ?? 'غير محدد',
                'supplied_materials_count' => count($prices),
                'latest_prices' => array_slice($prices, 0, 5),
                'associated_field_reports_count' => $reportsCount,
            ];
        })->all();

        return [
            'found' => count($suppliers) > 0,
            'suppliers_count' => count($suppliers),
            'suppliers' => $suppliers,
        ];
    }

    /**
     * Tool 10: Query resource price history, market rates, and supplier comparisons.
     */
    public function getResourcePricesAndMarketRates(User $user, array $args): array
    {
        $materialName = $args['material_name'] ?? null;
        $supplierName = $args['supplier_name'] ?? null;

        $query = resourcePrice::query()->with(['resource', 'supplier', 'location']);

        if (!empty($materialName)) {
            $query->whereHas('resource', function ($rq) use ($materialName) {
                $rq->where('name->ar', 'LIKE', "%{$materialName}%")
                    ->orWhere('name->en', 'LIKE', "%{$materialName}%")
                    ->orWhere('resource_code', 'LIKE', "%{$materialName}%");
            });
        }

        if (!empty($supplierName)) {
            $query->whereHas('supplier', function ($sq) use ($supplierName) {
                $sq->where('name->ar', 'LIKE', "%{$supplierName}%")
                    ->orWhere('name->en', 'LIKE', "%{$supplierName}%");
            });
        }

        $prices = $query->orderByDesc('effective_date')->take(20)->get();

        if ($prices->isEmpty()) {
            return [
                'found' => false,
                'message' => 'لم يتم العثور على سجلات أسعار تطابق البحث.',
            ];
        }

        $items = $prices->map(fn ($p) => [
            'resource_name' => $p->resource?->name ?? 'خامة غير محددة',
            'resource_code' => $p->resource?->resource_code ?? null,
            'supplier_name' => $p->supplier?->name ?? 'غير محدد',
            'location' => $p->location?->name ?? 'غير محدد',
            'price' => (float) $p->price,
            'tax_value' => (float) $p->tax_value,
            'price_after_tax' => (float) $p->price_after_tax,
            'currency' => $p->currency ?? 'SAR',
            'effective_date' => $p->effective_date,
            'is_current' => (bool) $p->is_current,
        ])->values()->all();

        $numericPrices = array_column($items, 'price');
        $minPrice = count($numericPrices) > 0 ? min($numericPrices) : 0;
        $maxPrice = count($numericPrices) > 0 ? max($numericPrices) : 0;
        $avgPrice = count($numericPrices) > 0 ? round(array_sum($numericPrices) / count($numericPrices), 2) : 0;

        return [
            'found' => true,
            'prices_count' => count($items),
            'price_range' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'avg_price' => $avgPrice,
            ],
            'prices' => $items,
        ];
    }

    /**
     * Tool 11: Global contextual & semantic omnisearch across the entire project and database.
     * Searches people, materials, units (e.g. ton/kg), projects, orders, suppliers, and site reports.
     */
    public function globalContextualSearch(User $user, array $args): array
    {
        $rawQuery = trim((string) ($args['query'] ?? ''));
        $intent = $args['intent'] ?? 'omnisearch';
        $personName = trim((string) ($args['person_name'] ?? ''));
        $materialName = trim((string) ($args['material_name'] ?? ''));
        $unitHint = trim((string) ($args['unit'] ?? ''));
        $projectName = trim((string) ($args['project_name'] ?? ''));
        $suppliedKeywords = (array) ($args['keywords'] ?? []);

        // 1. Tokenize & normalize keywords
        $allTokens = array_filter(array_unique(array_merge(
            $suppliedKeywords,
            preg_split('/\s+/u', $rawQuery, -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        // Remove stop words
        $stopWords = ['في', 'من', 'على', 'إلى', 'الى', 'عن', 'ما', 'هو', 'هي', 'كم', 'كل', 'اريد', 'أريد', 'عايز', 'بحث', 'باسم', 'جميع', 'هنا', 'النظام', 'المشروع', 'المشاريع', 'داتابيز', 'الداتابيز'];
        $cleanTokens = array_values(array_filter($allTokens, fn ($t) => !in_array($t, $stopWords, true) && mb_strlen($t) > 1));

        if (empty($cleanTokens) && !empty($allTokens)) {
            $cleanTokens = array_values($allTokens);
        }

        // Expand tokens with Arabic linguistic variants
        $expandedTokens = $this->expandArabicTokens($cleanTokens);

        // Detect known measurement units if not explicitly provided
        $knownUnits = ['طن', 'ton', 'tonne', 'كجم', 'كيلو', 'kg', 'متر', 'م2', 'م3', 'حبة', 'قطعة', 'piece'];
        if (empty($unitHint)) {
            foreach ($cleanTokens as $token) {
                if (in_array(mb_strtolower($token), $knownUnits, true)) {
                    $unitHint = $token;
                    break;
                }
            }
        }

        $results = [
            'query' => $rawQuery,
            'intent' => $intent,
            'detected_unit' => $unitHint ?: null,
            'matched_people' => [],
            'matched_materials' => [],
            'matched_projects' => [],
            'matched_orders' => [],
            'matched_suppliers' => [],
            'matched_reports' => [],
        ];

        // 2. Search People & Relational Footprint
        if ($personName !== '' || in_array($intent, ['person_search', 'person_and_assignments', 'omnisearch'], true)) {
            $peopleTokens = $personName !== '' ? $this->expandArabicTokens([$personName]) : $expandedTokens;
            $results['matched_people'] = $this->searchPeopleContext($user, $peopleTokens);
        }

        // 3. Search Materials & Stock
        if ($materialName !== '' || $unitHint !== '' || in_array($intent, ['material_search', 'material_stock_and_prices', 'omnisearch'], true)) {
            $matTokens = $materialName !== '' ? $this->expandArabicTokens([$materialName]) : $expandedTokens;
            $results['matched_materials'] = $this->searchMaterialsContext($user, $matTokens, $unitHint);
        }

        // 4. Search Projects
        if ($projectName !== '' || in_array($intent, ['project_search', 'project_overview', 'omnisearch'], true)) {
            $projTokens = $projectName !== '' ? $this->expandArabicTokens([$projectName]) : $expandedTokens;
            $results['matched_projects'] = $this->searchProjectsContext($user, $projTokens);
        }

        // 5. Search Orders & Procurement
        if (in_array($intent, ['procurement_search', 'orders_and_procurement', 'material_search', 'omnisearch'], true)) {
            $results['matched_orders'] = $this->searchOrdersContext($user, $expandedTokens, $unitHint);
        }

        // 6. Search Suppliers
        if (in_array($intent, ['supplier_search', 'pricing_and_suppliers', 'material_search', 'omnisearch'], true)) {
            $results['matched_suppliers'] = $this->searchSuppliersContext($user, $expandedTokens);
        }

        // 7. Search Site Reports
        if (in_array($intent, ['reports_search', 'omnisearch'], true) && count($results['matched_people']) === 0 && count($results['matched_materials']) === 0) {
            $results['matched_reports'] = $this->searchReportsContext($user, $expandedTokens);
        }

        $totalMatches = count($results['matched_people'])
            + count($results['matched_materials'])
            + count($results['matched_projects'])
            + count($results['matched_orders'])
            + count($results['matched_suppliers'])
            + count($results['matched_reports']);

        $results['total_matches'] = $totalMatches;
        $results['found'] = $totalMatches > 0;

        return $results;
    }

    /**
     * Expand Arabic tokens with prefixes and phonetic/orthographic variants.
     */
    public function expandArabicTokens(array $tokens): array
    {
        $expanded = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $expanded[] = $token;

            // Handle definite article (ال)
            if (mb_strpos($token, 'ال') === 0 && mb_strlen($token) > 3) {
                $expanded[] = mb_substr($token, 2);
            } else {
                $expanded[] = 'ال' . $token;
            }

            // Handle Alef variations (أ, إ, آ, ا)
            $alefPattern = '/[اأإآ]/u';
            if (preg_match($alefPattern, $token)) {
                $expanded[] = preg_replace($alefPattern, 'ا', $token);
                $expanded[] = preg_replace($alefPattern, 'أ', $token);
                $expanded[] = preg_replace($alefPattern, 'إ', $token);
                $expanded[] = preg_replace($alefPattern, 'آ', $token);
            }

            // Handle Taa Marbuta and Haa (ة, ه)
            if (mb_substr($token, -1) === 'ة') {
                $expanded[] = mb_substr($token, 0, -1) . 'ه';
            } elseif (mb_substr($token, -1) === 'ه') {
                $expanded[] = mb_substr($token, 0, -1) . 'ة';
            }

            // Handle Yaa and Alef Maqsura (ي, ى)
            if (mb_substr($token, -1) === 'ى') {
                $expanded[] = mb_substr($token, 0, -1) . 'ي';
            } elseif (mb_substr($token, -1) === 'ي') {
                $expanded[] = mb_substr($token, 0, -1) . 'ى';
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    /**
     * Search People and their complete relational footprint in the organization.
     */
    protected function searchPeopleContext(User $user, array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $query = User::query();
        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('name', 'LIKE', "%{$token}%")
                  ->orWhere('email', 'LIKE', "%{$token}%")
                  ->orWhere('mobile', 'LIKE', "%{$token}%");
            }
        });

        $users = $query->take(8)->get();

        return $users->map(function (User $u) use ($user) {
            // Assigned projects
            $engineerProjectIds = ProjectEngineers::where(function ($q) use ($u) {
                $q->where('engineer_id', $u->id);
                if (Schema::hasColumn('project_engineers', 'user_id')) {
                    $q->orWhere('user_id', $u->id);
                }
            })->pluck('project_id')->toArray();

            $ownedProjectIds = Project::where('user_id', $u->id)->pluck('id')->toArray();
            $allProjectIds = array_values(array_unique(array_merge($engineerProjectIds, $ownedProjectIds)));

            $projectsQuery = Project::whereIn('id', $allProjectIds);
            if (!$this->isAdmin($user)) {
                $projectsQuery->whereIn('id', $this->getAllowedProjectIds($user));
            }
            $projects = $projectsQuery->take(5)->get(['id', 'name', 'status', 'total_price'])->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'total_cost' => (float) ($p->total_price ?? 0),
            ])->all();

            // Supervised sites
            $sites = Site::whereIn('id', function ($sq) use ($u) {
                $sq->select('site_id')->from('site_user')->where('user_id', $u->id);
            })->with('project')->take(5)->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'project_name' => $s->project?->name ?? 'غير محدد',
            ])->all();

            $roleName = $u->type ?? 'موظف';
            if (class_exists(\Spatie\Permission\Models\Role::class) && Schema::hasTable('roles')) {
                $firstRole = $u->roles()->first();
                if ($firstRole) {
                    $roleName = $firstRole->name;
                }
            }

            $reportsCount = SiteReport::where('user_id', $u->id)->count();
            $ordersCount = Order::where('user_id', $u->id)->count();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'mobile' => $u->mobile,
                'role' => $roleName,
                'type' => $u->type,
                'assigned_projects_count' => count($allProjectIds),
                'assigned_projects' => $projects,
                'supervised_sites_count' => count($sites),
                'supervised_sites' => $sites,
                'field_reports_count' => $reportsCount,
                'orders_count' => $ordersCount,
            ];
        })->all();
    }

    /**
     * Search Materials, units, market prices, and project allocations.
     */
    protected function searchMaterialsContext(User $user, array $tokens, string $unitHint = ''): array
    {
        if (empty($tokens) && empty($unitHint)) {
            return [];
        }

        $with = ['category', 'resourcePrices.supplier'];
        if (Schema::hasTable('units')) {
            $with[] = 'unit';
        }
        $query = Resource::query()->with($with);

        $query->where(function ($q) use ($tokens, $unitHint) {
            foreach ($tokens as $token) {
                $q->orWhere('name->ar', 'LIKE', "%{$token}%")
                  ->orWhere('name->en', 'LIKE', "%{$token}%")
                  ->orWhere('resource_code', 'LIKE', "%{$token}%");
            }
            if ($unitHint !== '') {
                $q->orWhere('unit_of_measure->ar', 'LIKE', "%{$unitHint}%")
                  ->orWhere('unit_of_measure->en', 'LIKE', "%{$unitHint}%");

                if (Schema::hasTable('units')) {
                    $q->orWhereHas('unit', function ($uq) use ($unitHint) {
                        $uq->where('name->ar', 'LIKE', "%{$unitHint}%")
                           ->orWhere('name->en', 'LIKE', "%{$unitHint}%");
                    });
                }
            }
        });

        $resources = $query->take(8)->get();

        return $resources->map(function (Resource $r) use ($user) {
            $latestPrice = $r->resourcePrices->sortByDesc('effective_date')->first();

            // Current project stock
            $stockQuery = ProjectResources::where('resource_id', $r->id)->with('project');
            if (!$this->isAdmin($user)) {
                $stockQuery->whereIn('project_id', $this->getAllowedProjectIds($user));
            }
            $stockItems = $stockQuery->take(5)->get();
            $totalStock = (float) $stockQuery->sum('quantity');

            $projectAllocations = $stockItems->map(fn ($pi) => [
                'project_id' => $pi->project_id,
                'project_name' => $pi->project?->name ?? 'غير محدد',
                'quantity' => (float) $pi->quantity,
                'unit' => $pi->unit ?: ($r->unit?->name ?? 'وحدة'),
                'price' => (float) $pi->price,
            ])->all();

            $unitName = is_array($r->unit_of_measure) ? ($r->unit_of_measure['ar'] ?? $r->unit_of_measure['en'] ?? '') : (string) $r->unit_of_measure;
            if (empty($unitName) && $r->unit) {
                $unitName = $r->unit->name;
            }

            return [
                'id' => $r->id,
                'name' => $r->name,
                'resource_code' => $r->resource_code,
                'category' => $r->category?->name ?? 'مادة خام',
                'unit' => $unitName ?: 'وحدة قياس',
                'latest_price' => $latestPrice ? [
                    'price' => (float) $latestPrice->price,
                    'tax' => (float) $latestPrice->tax_value,
                    'price_after_tax' => (float) $latestPrice->price_after_tax,
                    'currency' => $latestPrice->currency ?? 'SAR',
                    'supplier' => $latestPrice->supplier?->name ?? 'غير محدد',
                    'effective_date' => $latestPrice->effective_date,
                ] : null,
                'total_stock_in_projects' => $totalStock,
                'project_stock_locations' => $projectAllocations,
            ];
        })->all();
    }

    /**
     * Search Projects and their status, sites, and assigned team.
     */
    protected function searchProjectsContext(User $user, array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $query = Project::query()->with(['users.engineer']);

        if (!$this->isAdmin($user)) {
            $query->whereIn('id', $this->getAllowedProjectIds($user));
        }

        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('name', 'LIKE', "%{$token}%")
                  ->orWhere('description', 'LIKE', "%{$token}%");
            }
        });

        $projects = $query->take(6)->get();

        return $projects->map(function (Project $p) {
            $sitesCount = Site::where('project_id', $p->id)->count();
            $engineers = $p->users->map(fn ($ue) => $ue->engineer?->name)->filter()->values()->all();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'description' => $p->description,
                'start_date' => $p->created_at ? $p->created_at->toDateString() : null,
                'end_date' => $p->end_date ? Carbon::parse($p->end_date)->toDateString() : null,
                'sites_count' => $sitesCount,
                'engineers' => $engineers,
                'total_cost' => (float) ($p->total_price ?? 0),
            ];
        })->all();
    }

    /**
     * Search Orders and line items matching the query.
     */
    protected function searchOrdersContext(User $user, array $tokens, string $unitHint = ''): array
    {
        if (empty($tokens)) {
            return [];
        }

        $query = OrderItems::query()->with(['order.user', 'resource', 'site.project']);

        if (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $query->whereHas('site', fn ($sq) => $sq->whereIn('project_id', $allowedProjectIds));
        }

        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('boq_item', 'LIKE', "%{$token}%")
                  ->orWhere('notes', 'LIKE', "%{$token}%")
                  ->orWhereHas('resource', function ($rq) use ($token) {
                      $rq->where('name->ar', 'LIKE', "%{$token}%")
                         ->orWhere('name->en', 'LIKE', "%{$token}%")
                         ->orWhere('resource_code', 'LIKE', "%{$token}%");
                  });
            }
        });

        return $query->latest()->take(6)->get()->map(fn (OrderItems $oi) => [
            'id' => $oi->id,
            'order_number' => $oi->order?->order_number ?? "ORD-{$oi->order_id}",
            'material_name' => $oi->resource?->name ?? $oi->boq_item ?? 'بند توريد',
            'quantity' => (float) $oi->quantity,
            'price' => (float) $oi->price,
            'total_after_tax' => (float) $oi->total_after_tax,
            'status' => is_object($oi->status) ? ($oi->status->value ?? (string) $oi->status) : (string) $oi->status,
            'project_name' => $oi->site?->project?->name ?? 'غير محدد',
            'requester' => $oi->order?->user?->name ?? 'غير محدد',
            'delivery_date' => $oi->delivery_date,
        ])->all();
    }

    /**
     * Search Suppliers offering materials or matching names.
     */
    protected function searchSuppliersContext(User $user, array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $query = Supplier::query()->with(['location', 'resourcePrices.resource']);

        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('name->ar', 'LIKE', "%{$token}%")
                  ->orWhere('name->en', 'LIKE', "%{$token}%")
                  ->orWhere('contact', 'LIKE', "%{$token}%")
                  ->orWhereHas('resourcePrices.resource', function ($rq) use ($token) {
                      $rq->where('name->ar', 'LIKE', "%{$token}%")
                         ->orWhere('name->en', 'LIKE', "%{$token}%");
                  });
            }
        });

        return $query->take(6)->get()->map(fn (Supplier $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'contact' => $s->contact,
            'email' => $s->email,
            'location' => $s->location?->name ?? 'غير محدد',
            'supplied_materials_sample' => $s->resourcePrices->take(3)->map(fn ($p) => $p->resource?->name)->filter()->values()->all(),
        ])->all();
    }

    /**
     * Search Site Reports for mentions of materials, problems, or notes.
     */
    protected function searchReportsContext(User $user, array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $query = SiteReport::query()->with(['site.project', 'user', 'resource']);

        if (!$this->isAdmin($user)) {
            $allowedProjectIds = $this->getAllowedProjectIds($user);
            $query->whereHas('site', fn ($sq) => $sq->whereIn('project_id', $allowedProjectIds));
        }

        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('report_content', 'LIKE', "%{$token}%")
                  ->orWhere('notes', 'LIKE', "%{$token}%");
            }
        });

        return $query->latest()->take(6)->get()->map(fn (SiteReport $sr) => [
            'id' => $sr->id,
            'project_name' => $sr->site?->project?->name ?? 'غير محدد',
            'site_name' => $sr->site?->name ?? 'غير محدد',
            'author' => $sr->user?->name ?? 'غير محدد',
            'report_type' => is_object($sr->report_type) ? ($sr->report_type->value ?? (string) $sr->report_type) : (string) $sr->report_type,
            'content' => Str::limit($sr->report_content ?? $sr->notes ?? '', 120),
            'date' => $sr->created_at ? $sr->created_at->toDateString() : null,
        ])->all();
    }
}
