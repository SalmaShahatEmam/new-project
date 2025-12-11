<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    protected static mixed $abilities;
    protected static mixed $models;
    protected static mixed $permissions;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();

        $this->defaultPermissions();
        $this->syncAdminRole();
    }

    public function defaultPermissions(): void
    {
        $this->adminRole(new RolesPermissionGenerator());
    }

    private function adminRole($rolesPermissionGenerator): void
    {
        # Models
        $adminModels = [
            'users', 'roles', 'permissions', 'notifications','settings' , "cities"
        ];
        # Default Methods
        $methods = ['index', 'create', 'edit', 'delete', 'show', 'activate'];
        # Additional Permissions
        $additionalAdminPermissions = [];
        # Generate
        $adminRole = Role::firstOrCreate([
            'name' => [
                'ar' => __('permissions.responses.roles-models.admin', [], 'ar'),
                'en' => __('permissions.responses.roles-models.admin', [], 'en')
            ],
            'guard_name' => 'api',
            'slug' => 'admin'
        ]);
        $adminRole->syncPermissions(
            $rolesPermissionGenerator->handle(
                $adminModels,
                $methods,
                'admin',
                additionalAdminPermissions: $additionalAdminPermissions
            )
        );
    }

    private function syncAdminRole(): void
    {
        User::whereRelation('roles', 'slug', 'LIKE', 'admin')
            ->get()
            ->each(function ($user) {
                $user->syncRoles([1]);
            });
    }
}
