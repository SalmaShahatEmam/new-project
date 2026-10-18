<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EstimationEngineerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            'projects',
            'resources',
            'categories',
            'locations',
            'units',
            "suppliers"
        ];

        $methods = ['index', 'create', 'edit', 'delete', 'show', 'activate'];

        $role = Role::updateOrCreate(
            [
                'slug' => 'estimation-engineer',
                'guard_name' => 'api',
            ],
            [
                'name' => [
                    'ar' => 'مهندس تسعير',
                    'en' => 'Estimation Engineer',
                ],
            ]
        );

        $role->syncPermissions(
            (new RolesPermissionGenerator)->handle($models, $methods, 'estimation-engineer')
        );
    }
}
