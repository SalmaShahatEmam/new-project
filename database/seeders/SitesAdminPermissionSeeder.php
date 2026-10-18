<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SitesAdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')
            ->where('guard_name', 'api')
            ->firstOrFail();

        $permissions = (new RolesPermissionGenerator)->handle(
            ['sites'],
            ['index', 'create', 'edit', 'delete', 'show', 'activate', 'add'],
            'admin'
        );

        $adminRole->givePermissionTo($permissions);
    }
}
