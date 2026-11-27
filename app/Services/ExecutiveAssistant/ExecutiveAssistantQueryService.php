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
        $projects = $query->with(['users.engineer'])
            ->orderByDesc('created_at')
            ->take(15)
            ->get()
            ->map(function (Project $p) {
                $engineers = $p->users->map(fn ($ue) => [
                    'name' => $ue->engineer?->name ?? 'غير محدد',
                    'type' => $ue->type ?? 'مهندس موقع',
                ])->values()->all();

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
}
