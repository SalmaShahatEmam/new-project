<?php

namespace App\Console\Commands;

use App\Actions\RolesPermissionGenerator;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class AddRolePermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Examples:
     *   php artisan role:add-permissions
     *   php artisan role:add-permissions site-engineer categories
     *   php artisan role:add-permissions site-engineer categories reports sites
     *   php artisan role:add-permissions site-engineer categories --methods=index,show,create
     *
     * @var string
     */
    protected $signature = 'role:add-permissions
                            {role? : The role slug (e.g. site-engineer, admin, estimation-engineer)}
                            {models?* : One or more model names (e.g. categories, reports, sites)}
                            {--methods= : Comma-separated list of methods (default: index,create,edit,delete,show,activate)}
                            {--guard=api : The guard name}
                            {--no-parent : Do not attach the parent model permission}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add model CRUD permissions to a role without replacing existing permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $guardName = $this->option('guard') ?: 'api';

        // 1. Resolve role slug
        $roleSlug = $this->argument('role');
        if (! $roleSlug) {
            $existingRoles = Role::where('guard_name', $guardName)->pluck('slug')->toArray();
            if (! empty($existingRoles)) {
                $roleSlug = $this->anticipate('Enter role slug', $existingRoles, 'site-engineer');
            } else {
                $roleSlug = $this->ask('Enter role slug', 'site-engineer');
            }
        }
        $roleSlug = trim($roleSlug);

        // 2. Resolve models
        $models = (array) $this->argument('models');
        if (empty($models)) {
            $modelInput = $this->ask('Enter model name(s) (comma-separated, e.g. categories, reports)', 'categories');
            $models = array_values(array_filter(array_map('trim', explode(',', $modelInput))));
        }

        if (empty($models)) {
            $this->error('No models specified. Operation cancelled.');
            return self::FAILURE;
        }

        // 3. Resolve methods
        $methodsOption = $this->option('methods');
        if (! empty($methodsOption)) {
            $methods = array_values(array_filter(array_map('trim', explode(',', $methodsOption))));
        } else {
            $methods = ['index', 'create', 'edit', 'delete', 'show', 'activate'];
        }

        // 4. Find or create the target Role
        $role = Role::where('slug', $roleSlug)
            ->where('guard_name', $guardName)
            ->first();

        if (! $role) {
            if ($this->confirm("Role '{$roleSlug}' does not exist. Create it?", true)) {
                $role = Role::create([
                    'slug' => $roleSlug,
                    'guard_name' => $guardName,
                    'name' => [
                        'ar' => $roleSlug,
                        'en' => Str::title(str_replace(['-', '_'], ' ', $roleSlug)),
                    ],
                ]);
                $this->info("Created role: [{$roleSlug}]");
            } else {
                $this->warn('Aborted.');
                return self::FAILURE;
            }
        }

        $this->line("Attaching permissions for role <comment>{$roleSlug}</comment>...");

        // 5. Generate permissions using project's RolesPermissionGenerator
        $generator = new RolesPermissionGenerator();
        $generatedPermissions = $generator->handle(
            models: $models,
            methods: $methods,
            guard_name: $roleSlug
        );

        $permissionsToAttach = collect($generatedPermissions);

        // 6. Include parent model permissions unless --no-parent is passed
        if (! $this->option('no-parent')) {
            $parentSlugs = array_map(fn ($m) => Str::slug($m), $models);
            $parentPermissions = Permission::whereIn('slug', $parentSlugs)
                ->where('guard_name', $guardName)
                ->get();

            $permissionsToAttach = $permissionsToAttach->merge($parentPermissions)->unique('id');
        }

        // 7. Attach to role WITHOUT replacing existing permissions
        $role->givePermissionTo($permissionsToAttach->all());

        // 8. Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 9. Display summary table
        $tableRows = $permissionsToAttach->map(function ($perm) use ($role) {
            return [
                'ID' => $perm->id,
                'Slug' => $perm->slug,
                'Model' => $perm->model ?: '-',
                'Role' => $role->slug,
                'Status' => 'Attached',
            ];
        })->toArray();

        $this->table(['ID', 'Slug', 'Model', 'Role', 'Status'], $tableRows);

        $count = $permissionsToAttach->count();
        $this->info("Successfully attached {$count} permission(s) to role '{$roleSlug}' without replacing existing permissions.");

        return self::SUCCESS;
    }
}
