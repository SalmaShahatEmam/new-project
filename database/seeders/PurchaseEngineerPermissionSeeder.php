<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PurchaseEngineerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            'resources',
            'categories',
            'locations',
            'suppliers',
            'units',
          //  'resourcePrices'
        ];

        $methods = ['index', 'create', 'edit', 'delete', 'show', 'activate'];

        $role = Role::updateOrCreate(
            [
                'slug' => 'purchase-engineer',
                'guard_name' => 'api',
            ],
            [
                'name' => [
                    'ar' => 'مهندس المشتريات',
                    'en' => 'Purchase Engineer',
                ],
            ]
        );

        $role->syncPermissions(
            (new RolesPermissionGenerator)->handle($models, $methods, 'purchase-engineer')
        );
    }
}
