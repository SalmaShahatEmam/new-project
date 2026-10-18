<?php

namespace App\Services\Assistant;

use App\Models\Location;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectEngineers;
use App\Models\ProjectResources;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\resourcePrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AssistantDatabaseAssistant
{
    private const NO_ANSWER = 'لم أجد بيانات كافية للإجابة عن هذا السؤال من قاعدة البيانات.';
    private const MUTATION_ANSWER = 'المساعد مخصص للاستعلام فقط ولا يمكنه إنشاء البيانات أو تعديلها أو حذفها.';

    public function isMutationQuestion(string $question): bool
    {
        if (preg_match('/(فين كود|الكود|ملف|function|method|class|كيف يعمل|إزاي بيتحسب|ازاي بيتحسب|طريقة حساب)/u', mb_strtolower($question))) {
            return false;
        }

        return (bool) preg_match('/\b(create|add|update|change|delete|remove|assign|approve|reject|set|make)\b|(?:ضيف|أضف|اضف|احذف|حذف|غيّر|غير|عدّل|عدل|خلي|اجعل|عيّن|عين|اعتمد|ارفض|وافق)\s+.{0,50}(?:سعر|دور|صلاح|مشروع|مستخدم|مهندس|مورد|موردين|resource|role|permission|project|user|price)/u', mb_strtolower($question));
    }

    public function isDatabaseQuestion(string $question): bool
    {
        $text = mb_strtolower($question);
        if (preg_match('/(فين كود|الكود|ملف|function|method|class|كيف يعمل|إزاي بيتحسب|ازاي بيتحسب|طريقة حساب)/u', $text)) {
            return false;
        }

        return (bool) preg_match('/(كام|كم|عدد|احسب|إحصائ|احصائ|هات|اعرض|قائمة|ابحث|مين|من هم|من هو|ما هي|ما هو|ما|مشروع|مشاريع|مورد|موارد|مادة|مواد|خامة|خامات|سعر|أسعار|اسعار|مستخدم|مستخدمين|موظف|موظفين|دور|أدوار|ادوار|صلاحية|صلاحيات|موقع|مواقع|وحدة|وحدات|مهندس|مهندسين|موردين|resource|material|project|supplier|price|user|employee|role|permission|location|unit|engineer|today|yesterday|month|week)/u', $text);
    }

    public function answer(string $question, string $apiKey, string $model): array
    {
        if ($this->isMutationQuestion($question)) {
            return ['answer' => self::MUTATION_ANSWER, 'sources' => []];
        }

        $tools = $this->toolsForQuestion($question);
        if ($tools === []) {
            return ['answer' => self::NO_ANSWER, 'sources' => []];
        }
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $selection = $this->chatCompletion($apiKey, $model, [
            [
                'role' => 'system',
                'content' => "You are a read-only admin database assistant. Choose only a supplied Laravel tool that directly answers the question. Never invent facts, SQL, tool names, database fields, or dates. If a result has multiple matches and the requested location/supplier/project is not unique, ask one concise clarification question. If the tool result says not_found or has no records, say no matching database record was found. If a result says a filter is unavailable, explain that the requested data cannot be safely determined from the current schema. Use the application date {$today} in timezone " . config('app.timezone') . '. Never request or expose credentials, passwords, OTPs, or authentication tokens.',
            ],
            ['role' => 'user', 'content' => $question],
        ], $tools);

        $toolCall = data_get($selection, 'choices.0.message.tool_calls.0');
        $toolName = data_get($toolCall, 'function.name');
        $allowedTools = array_map(fn (array $tool): string => $tool['function']['name'], $tools);
        if (! is_string($toolName) || ! in_array($toolName, $allowedTools, true)) {
            return ['answer' => self::NO_ANSWER, 'sources' => []];
        }

        $arguments = json_decode((string) data_get($toolCall, 'function.arguments', '{}'), true);
        if (! is_array($arguments)) {
            return ['answer' => self::NO_ANSWER, 'sources' => []];
        }

        try {
            $result = $this->execute($toolName, $arguments, $question);
        } catch (ValidationException) {
            return ['answer' => 'لم أتمكن من تحديد معايير البحث. أعد صياغة السؤال أو حدّد التاريخ/الاسم المطلوب.', 'sources' => []];
        }

        $toolCallId = data_get($toolCall, 'id', 'database-tool-call');
        $final = $this->chatCompletion($apiKey, $model, [
            [
                'role' => 'system',
                'content' => 'Answer concisely in the same language as the question using only the supplied Laravel tool result. Do not claim data that is not present. Treat not_found as no database match. If matches contain more than one possible record and the request did not specify a unique one, ask the user to clarify. Do not reveal internal tool names.',
            ],
            ['role' => 'user', 'content' => $question],
            data_get($selection, 'choices.0.message'),
            [
                'role' => 'tool',
                'tool_call_id' => $toolCallId,
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        ]);

        $answer = data_get($final, 'choices.0.message.content');
        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException('Assistant provider returned an empty answer.');
        }

        return ['answer' => trim($answer), 'sources' => []];
    }

    private function toolsForQuestion(string $question): array
    {
        $text = mb_strtolower($question);
        $tools = $this->toolDefinitions();
        $selected = [];
        $include = function (array $names) use (&$selected, $tools): void {
            foreach ($tools as $tool) {
                if (in_array($tool['function']['name'], $names, true)) {
                    $selected[] = $tool;
                }
            }
        };

        $isProject = (bool) preg_match('/(مشروع|مشاريع|project)/u', $text);
        $isResource = (bool) preg_match('/(مورد|موارد|مادة|مواد|خامة|خامات|حديد|resource|material)/u', $text);
        $isPrice = (bool) preg_match('/(سعر|أسعار|اسعار|price|prices|بكام)/u', $text);
        $isUser = (bool) preg_match('/(مستخدم|مستخدمين|موظف|موظفين|user|employee)/u', $text);
        $isRole = (bool) preg_match('/(دور|أدوار|ادوار|صلاحية|صلاحيات|role|permission)/u', $text);
        $isSupplier = (bool) preg_match('/(موردين|مورد|supplier)/u', $text);
        $isLocation = (bool) preg_match('/(موقع|مواقع|منطقة|location|region)/u', $text);
        $isUnit = (bool) preg_match('/(وحدة|وحدات|unit|قياس)/u', $text);
        $isAssignment = (bool) preg_match('/(مهندس|مهندسين|مسؤول|مسؤولين|تكليف|engineer|assignment)/u', $text);
        $isProjectResource = $isProject && $isResource;
        $isCount = (bool) preg_match('/(كام|كم|عدد|count|how many)/u', $text);
        $isOverview = (bool) preg_match('/(ملخص|نظرة عامة|overview|system summary)/u', $text);
        $isMultiOverview = $isCount && ((int) $isProject + (int) $isResource + (int) $isUser + (int) $isSupplier >= 3);

        if ($isOverview || $isMultiOverview) {
            $include(['system_overview']);
        }
        if ($isProject) {
            $include($isCount ? ['count_projects', 'project_status_summary'] : ['search_projects', 'project_status_summary']);
        }
        if ($isAssignment) {
            $include(['get_project_assignments']);
        }
        if ($isProjectResource) {
            $include(['get_project_resources']);
        }
        if ($isResource && ! $isProjectResource) {
            $include($isCount && ! $isPrice ? ['count_resources'] : ['search_resources']);
            if ($isPrice) {
                $include(['get_resource_price', 'get_resource_prices', 'get_resource_price_history']);
            }
        }
        if ($isPrice && ! $isResource && $isLocation) {
            $include(['get_resource_prices']);
        }
        if ($isUser) {
            $include($isCount ? ['count_users'] : ['search_users']);
            if ($isRole) {
                $include(['get_user_roles', 'get_user_access']);
            }
        }
        if ($isRole) {
            $include(['list_roles', 'get_role_permissions', 'list_permissions', 'get_user_roles']);
        }
        if ($isSupplier) {
            $include($isCount ? ['count_suppliers'] : ['search_suppliers']);
        }
        if ($isLocation) {
            $include(['list_locations']);
        }
        if ($isUnit) {
            $include(['list_units']);
        }

        return array_values(array_unique($selected, SORT_REGULAR));
    }

    private function toolDefinitions(): array
    {
        return [
            $this->tool('system_overview', 'Count projects, non-deleted resources, users, and suppliers.', []),
            $this->tool('count_projects', 'Count projects, optionally by creation date range and status.', [
                'from_date' => ['type' => 'string', 'description' => 'Optional inclusive start date YYYY-MM-DD'],
                'to_date' => ['type' => 'string', 'description' => 'Optional inclusive end date YYYY-MM-DD'],
                'status' => ['type' => 'string', 'description' => 'Optional exact project status'],
            ]),
            $this->tool('search_projects', 'Search projects by name, id, status, or location.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional project name or number'],
                'status' => ['type' => 'string', 'description' => 'Optional exact project status'],
                'location' => ['type' => 'string', 'description' => 'Optional location name or code'],
                'owner' => ['type' => 'string', 'description' => 'Optional project owner name or user id'],
            ]),
            $this->tool('project_status_summary', 'Count projects grouped by actual statuses stored in the database.', []),
            $this->tool('get_project_assignments', 'Find active engineer assignments for a project or projects assigned to an engineer.', [
                'project' => ['type' => 'string', 'description' => 'Project name or id'],
                'engineer' => ['type' => 'string', 'description' => 'Engineer name or id'],
            ]),
            $this->tool('get_project_resources', 'List materials on a project, their quantity, stored unit price, section, supply location and uploader.', [
                'project' => ['type' => 'string', 'description' => 'Project name or id'],
                'resource' => ['type' => 'string', 'description' => 'Optional material name or code'],
            ]),
            $this->tool('count_resources', 'Count non-deleted resources, optionally active only.', [
                'active' => ['type' => 'boolean', 'description' => 'Optional active state'],
            ]),
            $this->tool('search_resources', 'Search resources by bilingual name, code, category, unit or active state.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional name, code, category or unit'],
                'active' => ['type' => 'boolean', 'description' => 'Optional active state'],
                'missing_current_price' => ['type' => 'boolean', 'description' => 'Only resources without a current price'],
            ]),
            $this->tool('get_resource_price', 'Find the latest current price for a resource; preserve multiple locations/suppliers rather than choosing arbitrarily.', [
                'resource' => ['type' => 'string', 'description' => 'Resource name or code'],
                'date' => ['type' => 'string', 'description' => 'Optional effective date YYYY-MM-DD'],
                'location' => ['type' => 'string', 'description' => 'Optional location name or code'],
                'supplier' => ['type' => 'string', 'description' => 'Optional supplier name'],
            ]),
            $this->tool('get_resource_prices', 'List current prices effective on a date, with optional resource/location/supplier filters.', [
                'resource' => ['type' => 'string', 'description' => 'Optional resource name or code'],
                'date' => ['type' => 'string', 'description' => 'Optional effective date YYYY-MM-DD'],
                'location' => ['type' => 'string', 'description' => 'Optional location name or code'],
                'supplier' => ['type' => 'string', 'description' => 'Optional supplier name'],
            ]),
            $this->tool('get_resource_price_history', 'Return recorded historical resource prices ordered by effective date.', [
                'resource' => ['type' => 'string', 'description' => 'Resource name or code'],
                'from_date' => ['type' => 'string', 'description' => 'Optional start date YYYY-MM-DD'],
                'to_date' => ['type' => 'string', 'description' => 'Optional end date YYYY-MM-DD'],
                'location' => ['type' => 'string', 'description' => 'Optional location name or code'],
                'supplier' => ['type' => 'string', 'description' => 'Optional supplier name'],
            ]),
            $this->tool('count_users', 'Count non-deleted users with optional type and active state.', [
                'type' => ['type' => 'string', 'description' => 'Optional user type'],
                'active' => ['type' => 'boolean', 'description' => 'Optional active state'],
                'status' => ['type' => 'string', 'description' => 'Optional status only when available in the database'],
            ]),
            $this->tool('search_users', 'Search users by name, type, state, or role. Return only non-sensitive fields.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional name'],
                'type' => ['type' => 'string', 'description' => 'Optional user type'],
                'active' => ['type' => 'boolean', 'description' => 'Optional active state'],
                'status' => ['type' => 'string', 'description' => 'Optional status only when available in the database'],
                'role' => ['type' => 'string', 'description' => 'Optional role name'],
            ]),
            $this->tool('get_user_access', 'Return a user name, assigned roles and effective permission names.', [
                'user' => ['type' => 'string', 'description' => 'User id or name'],
            ]),
            $this->tool('list_roles', 'List role names and how many users have each role.', []),
            $this->tool('get_role_permissions', 'List permissions attached to a role.', [
                'role' => ['type' => 'string', 'description' => 'Role name'],
            ]),
            $this->tool('list_permissions', 'List permission names and for_users values.', []),
            $this->tool('get_user_roles', 'List role names assigned to a user.', [
                'user' => ['type' => 'string', 'description' => 'User id or name'],
            ]),
            $this->tool('search_suppliers', 'Search suppliers by bilingual name, status or resource-price association.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional supplier name'],
                'status' => ['type' => 'string', 'description' => 'Optional active or inactive status'],
                'resource' => ['type' => 'string', 'description' => 'Optional resource name/code for linked prices'],
            ]),
            $this->tool('count_suppliers', 'Count suppliers, optionally by active or inactive status.', [
                'status' => ['type' => 'string', 'description' => 'Optional active or inactive status'],
            ]),
            $this->tool('list_locations', 'List locations with their names, codes and active states.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional location name or code'],
            ]),
            $this->tool('list_units', 'List measurement units and active states.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional unit name'],
                'active' => ['type' => 'boolean', 'description' => 'Optional active state'],
            ]),
        ];
    }

    private function tool(string $name, string $description, array $properties): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function execute(string $tool, array $arguments, string $question): array
    {
        return match ($tool) {
            'system_overview' => $this->systemOverview(),
            'count_projects' => $this->countProjects($arguments, $question),
            'search_projects' => $this->searchProjects($arguments),
            'project_status_summary' => ['statuses' => Project::query()->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->orderBy('status')->get()->toArray()],
            'get_project_assignments' => $this->projectAssignments($arguments),
            'get_project_resources' => $this->projectResources($arguments),
            'count_resources' => $this->countResources($arguments),
            'search_resources' => $this->searchResources($arguments),
            'get_resource_price', 'get_resource_prices' => $this->resourcePrices($arguments, $question, false),
            'get_resource_price_history' => $this->resourcePrices($arguments, $question, true),
            'count_users' => $this->countUsers($arguments),
            'search_users' => $this->searchUsers($arguments),
            'get_user_access' => $this->userAccess($arguments, true),
            'list_roles' => $this->listRoles(),
            'get_role_permissions' => $this->rolePermissions($arguments),
            'list_permissions' => $this->listPermissions(),
            'get_user_roles' => $this->userAccess($arguments, false),
            'search_suppliers' => $this->searchSuppliers($arguments),
            'count_suppliers' => $this->countSuppliers($arguments),
            'list_locations' => $this->listLocations($arguments),
            'list_units' => $this->listUnits($arguments),
            default => throw new RuntimeException('Unsupported assistant database tool.'),
        };
    }

    private function systemOverview(): array
    {
        return [
            'projects' => Project::query()->count(),
            'resources' => Resource::query()->count(),
            'users' => User::query()->count(),
            'suppliers' => Supplier::query()->count(),
        ];
    }

    private function countProjects(array $arguments, string $question): array
    {
        $data = $this->validate($arguments, [
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
        $range = $this->relativeDateRange($question);
        $from = $range[0] ?? $data['from_date'] ?? null;
        $to = $range[1] ?? $data['to_date'] ?? null;
        if (($from === null) !== ($to === null)) {
            throw ValidationException::withMessages(['dates' => 'Both date bounds are required.']);
        }
        if ($from !== null && $from > $to) {
            throw ValidationException::withMessages(['dates' => 'The start date must not be after the end date.']);
        }

        $query = Project::query();
        if ($from !== null) {
            $query->whereBetween('created_at', [
                CarbonImmutable::parse($from, config('app.timezone'))->startOfDay(),
                CarbonImmutable::parse($to, config('app.timezone'))->endOfDay(),
            ]);
        }
        if (! empty($data['status'])) {
            $query->whereRaw('LOWER(status) = ?', [mb_strtolower($data['status'])]);
        }

        return ['count' => $query->count(), 'from_date' => $from, 'to_date' => $to, 'status' => $data['status'] ?? null];
    }

    private function searchProjects(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:100'],
            'owner' => ['nullable', 'string', 'max:100'],
        ]);
        $query = Project::query();
        if (! empty($data['keyword'])) {
            $keyword = $data['keyword'];
            $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$keyword}%")
                ->orWhere('id', ctype_digit($keyword) ? (int) $keyword : -1));
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['location']) && Schema::hasColumn('projects', 'location_id')) {
            $location = $data['location'];
            $locationIds = Location::query()->where(fn (Builder $locationQuery) => $locationQuery
                ->where('code', $location)
                ->orWhere('name->en', 'like', "%{$location}%")
                ->orWhere('name->ar', 'like', "%{$location}%"))
                ->pluck('id');
            $query->whereIn('location_id', $locationIds);
        } elseif (! empty($data['location'])) {
            return ['filter_unavailable' => 'location'];
        }
        if (! empty($data['owner'])) {
            if (! Schema::hasColumn('projects', 'user_id')) {
                return ['filter_unavailable' => 'project owner'];
            }
            $owner = $data['owner'];
            $ownerIds = ctype_digit($owner)
                ? [(int) $owner]
                : User::query()->where('name', 'like', "%{$owner}%")->pluck('id')->all();
            $query->whereIn('user_id', $ownerIds);
        }

        $projects = $query->orderByDesc('id')->limit(50)->get();
        return ['matches' => $projects->map(fn (Project $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'user_id' => $project->user_id,
            'location_id' => $project->location_id ?? null,
            'end_date' => $project->end_date,
        ])->all()];
    }

    private function projectAssignments(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'project' => ['nullable', 'string', 'max:100'],
            'engineer' => ['nullable', 'string', 'max:100'],
        ]);
        if (empty($data['project']) && empty($data['engineer'])) {
            return ['clarification_required' => true, 'message' => 'Specify a project or engineer.'];
        }
        $query = ProjectEngineers::query()->with([
            'project:id,name,status',
            'engineer:id,name,type',
        ]);
        if (! empty($data['project'])) {
            $project = $this->matchingProjects($data['project']);
            if ($project['ambiguous'] || $project['not_found']) {
                return $project;
            }
            $query->where('project_id', $project['project']->id);
        }
        if (! empty($data['engineer'])) {
            $engineer = $data['engineer'];
            $query->whereHas('engineer', fn (Builder $userQuery) => $this->matchUser($userQuery, $engineer));
        }
        if (Schema::hasColumn('project_engineers', 'active')) {
            $query->where('active', true);
        }
        return ['assignments' => $query->limit(100)->get()->map(fn (ProjectEngineers $assignment): array => [
            'project' => $assignment->project?->name,
            'project_id' => $assignment->project_id,
            'engineer' => $assignment->engineer?->name,
            'engineer_id' => $assignment->engineer_id,
            'type' => $assignment->type,
        ])->all()];
    }

    private function projectResources(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'project' => ['nullable', 'string', 'max:100'],
            'resource' => ['nullable', 'string', 'max:100'],
        ]);
        if (empty($data['project'])) {
            return ['clarification_required' => true, 'message' => 'project is required'];
        }
        $project = $this->matchingProjects($data['project']);
        if ($project['ambiguous'] || $project['not_found']) {
            return $project;
        }

        $query = ProjectResources::query()->with(['resource', 'projectEngineer:id,name', 'uploader:id,name']);
        $query->where('project_id', $project['project']->id);
        if (! empty($data['resource'])) {
            $resource = $data['resource'];
            $query->whereHas('resource', fn (Builder $resourceQuery) => $this->matchResource($resourceQuery, $resource));
        }
        $rows = $query->limit(200)->get();
        $items = $rows->map(function (ProjectResources $row): array {
            $quantity = is_numeric($row->quantity) ? (float) $row->quantity : null;
            $unitPrice = is_numeric($row->price) ? (float) $row->price : null;
            $location = is_numeric($row->supply_location)
                ? Location::query()->find((int) $row->supply_location)
                : null;
            $unitName = is_numeric($row->unit) ? Unit::query()->find((int) $row->unit)?->name : $row->unit;

            return [
                'resource' => $row->resource?->name,
                'resource_code' => $row->resource?->resource_code,
                'quantity' => $row->quantity,
                'unit' => $unitName,
                'unit_price' => $row->price,
                'line_total' => $quantity !== null && $unitPrice !== null ? round($quantity * $unitPrice, 2) : null,
                'section' => $row->section_name ?? null,
                'supply_location' => $location?->name ?? $row->supply_location,
                'engineer' => $row->projectEngineer?->name,
                'added_by' => $row->uploader?->name,
            ];
        });

        return [
            'project' => $project['project']->name,
            'price_basis' => 'ProjectController stores the selected resource price separately from quantity; line_total is quantity multiplied by that stored unit price.',
            'resources' => $items->all(),
            'total_cost' => $items->contains(fn (array $item): bool => $item['line_total'] === null)
                ? null
                : round($items->sum('line_total'), 2),
        ];
    }

    private function countResources(array $arguments): array
    {
        $data = $this->validate($arguments, ['active' => ['nullable', 'boolean']]);
        $query = Resource::query();
        $activeColumn = $this->resourceActiveColumn();
        if (array_key_exists('active', $data) && $data['active'] !== null && $activeColumn !== null) {
            $query->where($activeColumn, $data['active']);
        }
        return ['count' => $query->count(), 'active' => $data['active'] ?? null];
    }

    private function searchResources(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'keyword' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'missing_current_price' => ['nullable', 'boolean'],
        ]);
        $query = Resource::query()->without(['category', 'unit', 'resourcePrices'])->with('category');
        if (! empty($data['keyword'])) {
            $keyword = $data['keyword'];
            $query->where(function (Builder $query) use ($keyword): void {
                $this->matchResource($query, $keyword);
                $query->orWhere('unit_of_measure', 'like', "%{$keyword}%")
                    ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                        ->where('name->en', 'like', "%{$keyword}%")
                        ->orWhere('name->ar', 'like', "%{$keyword}%"));
                if (Schema::hasColumn('resources', 'unit_id')) {
                    $query->orWhereHas('unit', fn (Builder $unitQuery) => $unitQuery
                        ->where('name->en', 'like', "%{$keyword}%")
                        ->orWhere('name->ar', 'like', "%{$keyword}%"));
                }
            });
        }
        $activeColumn = $this->resourceActiveColumn();
        if (array_key_exists('active', $data) && $data['active'] !== null && $activeColumn !== null) {
            $query->where($activeColumn, $data['active']);
        }
        if (! empty($data['missing_current_price'])) {
            $query->whereDoesntHave('resourcePrices', fn (Builder $priceQuery) => $priceQuery->where('is_current', true));
        }
        return ['resources' => $query->limit(100)->get()->map(fn (Resource $resource): array => [
            'id' => $resource->id,
            'name' => $resource->name,
            'code' => $resource->resource_code,
            'category_id' => $resource->category_id,
            'category' => $resource->category?->name,
            'unit_of_measure' => $resource->unit_of_measure,
            'active' => $activeColumn !== null ? $resource->getAttribute($activeColumn) : null,
        ])->all()];
    }

    private function resourcePrices(array $arguments, string $question, bool $history): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:100'],
        ]);
        $relative = $this->relativeDateRange($question);
        $date = $relative[1] ?? $data['date'] ?? CarbonImmutable::now(config('app.timezone'))->toDateString();
        $query = resourcePrice::query()->with([
            'resource' => fn ($resourceQuery) => $resourceQuery->without(['category', 'unit', 'resourcePrices']),
            'location',
            'supplier',
        ]);
        if (! empty($data['resource'])) {
            $resource = $data['resource'];
            $query->whereHas('resource', fn (Builder $resourceQuery) => $this->matchResource($resourceQuery, $resource));
        }
        if (! empty($data['location'])) {
            $location = $data['location'];
            $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery
                ->where('code', $location)
                ->orWhere('name->en', 'like', "%{$location}%")
                ->orWhere('name->ar', 'like', "%{$location}%"));
        }
        if (! empty($data['supplier']) && Schema::hasColumn('resource_prices', 'supplier_id')) {
            $supplier = $data['supplier'];
            $query->whereHas('supplier', fn (Builder $supplierQuery) => $this->matchSupplier($supplierQuery, $supplier));
        }
        if ($history) {
            if (! empty($data['from_date'])) {
                $query->whereDate('effective_date', '>=', $data['from_date']);
            }
            if (! empty($data['to_date'])) {
                $query->whereDate('effective_date', '<=', $data['to_date']);
            }
        } else {
            $query->where('is_current', true)->whereDate('effective_date', '<=', $date);
        }

        $records = $query->orderByDesc('effective_date')->limit(300)->get();
        if (! $history) {
            $records = $records->unique(fn (resourcePrice $price): string => implode(':', [
                $price->resource_id,
                $price->location_id,
                Schema::hasColumn('resource_prices', 'supplier_id') ? ($price->supplier_id ?? 0) : 0,
            ]));
        }
        $prices = $records->take(100)->map(fn (resourcePrice $price): array => [
            'resource' => $price->resource?->name,
            'resource_code' => $price->resource?->resource_code,
            'unit' => $price->resource?->unit_of_measure,
            'price' => $price->price,
            'price_after_tax' => Schema::hasColumn('resource_prices', 'tax_value') ? $price->price_after_tax : $price->price,
            'currency' => $price->currency,
            'location' => $price->location?->name,
            'supplier' => $price->supplier?->name,
            'effective_date' => $price->effective_date,
        ])->values()->all();

        return ['as_of' => $history ? null : $date, 'history' => $history, 'prices' => $prices];
    }

    private function countUsers(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'type' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
        $query = User::query();
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (array_key_exists('active', $data) && $data['active'] !== null && Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', $data['active']);
        }
        if (! empty($data['status'])) {
            if (! Schema::hasColumn('users', 'status')) {
                return ['filter_unavailable' => 'user status'];
            }
            $query->where('status', $data['status']);
        }
        return ['count' => $query->count(), 'type' => $data['type'] ?? null, 'active' => $data['active'] ?? null];
    }

    private function searchUsers(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'keyword' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);
        $query = User::query()->select(['id', 'name', 'type', 'is_active']);
        if (! empty($data['keyword'])) {
            $query->where('name', 'like', '%' . $data['keyword'] . '%');
        }
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (array_key_exists('active', $data) && $data['active'] !== null && Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', $data['active']);
        }
        if (! empty($data['status'])) {
            if (! Schema::hasColumn('users', 'status')) {
                return ['filter_unavailable' => 'user status'];
            }
            $query->where('status', $data['status']);
        }
        if (! empty($data['role'])) {
            $query->role($data['role']);
        }
        return ['users' => $query->limit(100)->get()->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->type,
            'active' => $user->is_active ?? null,
        ])->all()];
    }

    private function userAccess(array $arguments, bool $includePermissions): array
    {
        $data = $this->validate($arguments, ['user' => ['required', 'string', 'max:100']]);
        $user = $this->matchingUser($data['user']);
        if ($user['ambiguous'] || $user['not_found']) {
            return $user;
        }
        $model = $user['user'];
        $result = ['user' => ['id' => $model->id, 'name' => $model->name], 'roles' => $model->getRoleNames()->all()];
        if ($includePermissions) {
            $result['permissions'] = $model->getAllPermissions()->pluck('name')->all();
        }
        return $result;
    }

    private function listRoles(): array
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->limit(100)->get();
        return ['roles' => $roles->map(fn (Role $role): array => [
            'name' => $role->name,
            'users_count' => User::role($role)->count(),
        ])->all()];
    }

    private function rolePermissions(array $arguments): array
    {
        $data = $this->validate($arguments, ['role' => ['required', 'string', 'max:100']]);
        $roles = Role::query()->with('permissions')->where('name', 'like', '%' . $data['role'] . '%')->limit(20)->get();
        if ($roles->isEmpty()) {
            return ['not_found' => true, 'role' => $data['role']];
        }
        return ['roles' => $roles->map(fn (Role $role): array => [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ])->all()];
    }

    private function listPermissions(): array
    {
        $permissions = Permission::query()->orderBy('name')->limit(200)->get();
        return ['permissions' => $permissions->map(fn (Permission $permission): array => [
            'name' => $permission->name,
            'for_users' => $permission->for_users ?? null,
        ])->all()];
    }

    private function searchSuppliers(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'resource' => ['nullable', 'string', 'max:100'],
        ]);
        $query = Supplier::query();
        if (! empty($data['keyword'])) {
            $this->matchSupplier($query, $data['keyword']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['resource']) && Schema::hasColumn('resource_prices', 'supplier_id')) {
            $resource = $data['resource'];
            $query->whereHas('resourcePrices.resource', fn (Builder $resourceQuery) => $this->matchResource($resourceQuery, $resource));
        } elseif (! empty($data['resource'])) {
            return ['filter_unavailable' => 'supplier resource association'];
        }
        return ['suppliers' => $query->limit(100)->get()->map(fn (Supplier $supplier): array => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'status' => $supplier->status,
            'location_id' => $supplier->location_id ?? null,
        ])->all()];
    }

    private function countSuppliers(array $arguments): array
    {
        $data = $this->validate($arguments, ['status' => ['nullable', 'in:active,inactive']]);
        $query = Supplier::query();
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        return ['count' => $query->count(), 'status' => $data['status'] ?? null];
    }

    private function listLocations(array $arguments): array
    {
        $data = $this->validate($arguments, ['keyword' => ['nullable', 'string', 'max:100']]);
        $query = Location::query();
        if (! empty($data['keyword'])) {
            $keyword = $data['keyword'];
            $query->where(fn (Builder $query) => $query->where('code', $keyword)
                ->orWhere('name->en', 'like', "%{$keyword}%")
                ->orWhere('name->ar', 'like', "%{$keyword}%"));
        }
        return ['locations' => $query->limit(100)->get()->map(fn (Location $location): array => [
            'name' => $location->name,
            'code' => $location->code,
            'active' => $location->is_active ?? null,
        ])->all()];
    }

    private function listUnits(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'keyword' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);
        $query = Unit::query();
        if (! empty($data['keyword'])) {
            $keyword = $data['keyword'];
            $query->where(fn (Builder $query) => $query->where('name->en', 'like', "%{$keyword}%")
                ->orWhere('name->ar', 'like', "%{$keyword}%"));
        }
        if (array_key_exists('active', $data) && $data['active'] !== null && Schema::hasColumn('units', 'is_active')) {
            $query->where('is_active', $data['active']);
        }
        return ['units' => $query->limit(100)->get()->map(fn (Unit $unit): array => [
            'id' => $unit->id,
            'name' => $unit->name,
            'active' => $unit->is_active ?? null,
        ])->all()];
    }

    private function matchingProjects(string $keyword): array
    {
        $query = Project::query();
        if (ctype_digit($keyword)) {
            $query->where('id', (int) $keyword);
        } else {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $matches = $query->limit(10)->get(['id', 'name', 'status']);
        if ($matches->isEmpty()) {
            return ['not_found' => true, 'project' => $keyword];
        }
        if ($matches->count() > 1) {
            return ['ambiguous' => true, 'matches' => $matches->map(fn (Project $project): array => ['id' => $project->id, 'name' => $project->name])->all()];
        }
        return ['project' => $matches->first(), 'not_found' => false, 'ambiguous' => false];
    }

    private function matchingUser(string $keyword): array
    {
        $query = User::query();
        if (ctype_digit($keyword)) {
            $query->where('id', (int) $keyword);
        } else {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $matches = $query->limit(10)->get(['id', 'name']);
        if ($matches->isEmpty()) {
            return ['not_found' => true, 'user' => $keyword];
        }
        if ($matches->count() > 1) {
            return ['ambiguous' => true, 'matches' => $matches->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all()];
        }
        return ['user' => $matches->first(), 'not_found' => false, 'ambiguous' => false];
    }

    private function matchResource(Builder $query, string $keyword): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('resource_code', $keyword)
            ->orWhere('name->en', 'like', "%{$keyword}%")
            ->orWhere('name->ar', 'like', "%{$keyword}%"));
    }

    private function matchSupplier(Builder $query, string $keyword): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('name->en', 'like', "%{$keyword}%")
            ->orWhere('name->ar', 'like', "%{$keyword}%"));
    }

    private function matchUser(Builder $query, string $keyword): Builder
    {
        if (ctype_digit($keyword)) {
            return $query->where('id', (int) $keyword);
        }
        return $query->where('name', 'like', "%{$keyword}%");
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }

    private function resourceActiveColumn(): ?string
    {
        if (Schema::hasColumn('resources', 'active')) {
            return 'active';
        }
        return Schema::hasColumn('resources', 'is_active') ? 'is_active' : null;
    }

    private function relativeDateRange(string $question): ?array
    {
        $text = mb_strtolower($question);
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        if (preg_match('/\b(today|النهارده|النهاردة|اليوم)\b/u', $text)) {
            return [$today->toDateString(), $today->toDateString()];
        }
        if (preg_match('/\b(yesterday|امبارح|أمس|مبارح)\b/u', $text)) {
            $date = $today->subDay()->toDateString();
            return [$date, $date];
        }
        if (preg_match('/\b(this month|الشهر الحالي|الشهر ده|هذا الشهر)\b/u', $text)) {
            return [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()];
        }
        if (preg_match('/\b(last month|الشهر الماضي|الشهر السابق|الشهر اللي فات)\b/u', $text)) {
            $month = $today->subMonthNoOverflow();
            return [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];
        }
        if (preg_match('/\b(this week|الأسبوع الحالي|هذا الأسبوع|الاسبوع الحالي|هذا الاسبوع)\b/u', $text)) {
            return [$today->startOfWeek()->toDateString(), $today->endOfWeek()->toDateString()];
        }
        if (preg_match('/\b(last week|الأسبوع الماضي|الاسبوع الماضي)\b/u', $text)) {
            $week = $today->subWeek();
            return [$week->startOfWeek()->toDateString(), $week->endOfWeek()->toDateString()];
        }
        return null;
    }

    private function chatCompletion(string $apiKey, string $model, array $messages, ?array $tools = null): array
    {
        $payload = ['model' => $model, 'messages' => $messages];
        if ($tools !== null) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('services.assistant.timeout', 45))
            ->post(rtrim(config('services.assistant.base_url'), '/') . '/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Assistant provider returned HTTP ' . $response->status() . '.');
        }

        return $response->json();
    }
}