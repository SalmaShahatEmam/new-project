<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * --------------------------------------------------------------------------
     * Configuration Properties
     * --------------------------------------------------------------------------
     * You can edit these values directly in the file, pass them via environment
     * variables (SEED_ROLE, SEED_MODEL), call the fluent methods programmatically,
     * or enter them interactively when running:
     *
     *   php artisan db:seed --class=RolePermissionSeeder
     */

    /**
     * The role slug to attach permissions to.
     * Examples: 'admin', 'site-engineer', 'estimation-engineer', 'purchase-engineer'
     */
    public string $roleSlug = 'site-engineer';

    /**
     * The model or models to generate permissions for.
     * Can be a string ('sites') or an array (['sites', 'reports', 'resources']).
     */
    public string|array $models = ['categories'];

    /**
     * The CRUD methods to generate for each model.
     * Generates: {model}-index, {model}-create, {model}-edit, {model}-delete, etc.
     */
    public array $methods = [
        'index',
        'create',
        'edit',
        'delete',
        'show',
        'activate',
    ];

    /**
     * Guard name for role and permissions (default 'api').
     */
    public string $guardName = 'api';

    /**
     * Whether to also attach the parent model permission (e.g. 'sites') along with action permissions.
     */
    public bool $includeParentPermission = true;

    /**
     * Set the role slug fluently.
     */
    public function forRole(string $roleSlug): self
    {
        $this->roleSlug = $roleSlug;

        return $this;
    }

    /**
     * Set the model or models fluently.
     */
    public function forModels(string|array $models): self
    {
        $this->models = $models;

        return $this;
    }

    /**
     * Set the methods fluently.
     */
    public function withMethods(array $methods): self
    {
        $this->methods = $methods;

        return $this;
    }

    /**
     * Run the database seed.
     */
    public function run(): void
    {
        $roleSlug = $this->resolveRoleSlug();
        $models = $this->resolveModels();
        $methods = $this->methods;

        // 1. Find or create the target Role
        $role = Role::where('slug', $roleSlug)
            ->where('guard_name', $this->guardName)
            ->first();

        if (! $role) {
            $role = Role::create([
                'slug' => $roleSlug,
                'guard_name' => $this->guardName,
                'name' => [
                    'ar' => $roleSlug,
                    'en' => Str::title(str_replace(['-', '_'], ' ', $roleSlug)),
                ],
            ]);

            if ($this->command) {
                $this->command->info("Created new role: [{$roleSlug}]");
            }
        }

        // 2. Generate permissions using the project's RolesPermissionGenerator
        $generator = new RolesPermissionGenerator();
        $generatedPermissions = $generator->handle(
            models: $models,
            methods: $methods,
            guard_name: $roleSlug
        );

        $permissionsToAttach = collect($generatedPermissions);

        // 3. Optionally include the parent model permissions (e.g. 'sites', 'reports')
        if ($this->includeParentPermission) {
            $parentSlugs = array_map(fn ($m) => Str::slug($m), $models);
            $parentPermissions = Permission::whereIn('slug', $parentSlugs)
                ->where('guard_name', $this->guardName)
                ->get();

            $permissionsToAttach = $permissionsToAttach->merge($parentPermissions)->unique('id');
        }

        // 4. Attach new permissions to the role WITHOUT replacing existing permissions
        $role->givePermissionTo($permissionsToAttach->all());

        // 5. Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 6. Output result summary
        if ($this->command) {
            $attachedCount = $permissionsToAttach->count();
            $modelList = implode(', ', $models);
            $methodList = implode(', ', $methods);
            $this->command->info("Added {$attachedCount} permission(s) for [{$modelList}] ({$methodList}) to role '{$roleSlug}' without replacing existing permissions.");
        }
    }

    /**
     * Resolve the target role slug.
     */
    protected function resolveRoleSlug(): string
    {
        $envRole = env('SEED_ROLE');
        if (! empty($envRole)) {
            return trim($envRole);
        }

        return $this->roleSlug;
    }

    /**
     * Resolve the models array.
     */
    protected function resolveModels(): array
    {
        $envModels = env('SEED_MODELS', env('SEED_MODEL'));
        if (! empty($envModels)) {
            return array_values(array_filter(array_map('trim', explode(',', $envModels))));
        }

        return is_array($this->models) ? $this->models : [$this->models];
    }
}
