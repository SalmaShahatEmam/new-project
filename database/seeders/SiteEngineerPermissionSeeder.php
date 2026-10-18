<?php

namespace Database\Seeders;

use App\Actions\RolesPermissionGenerator;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SiteEngineerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $generator = new RolesPermissionGenerator;
        $models = [
            'sites',
            'projects',       
            'locations',
            'resources',
            'suppliers',
            'units',
            'chats',
        ];
        $methods = ['index', 'create', 'edit', 'delete', 'show', 'activate'];

        $role = Role::updateOrCreate(
            [
                'slug' => 'site-engineer',
                'guard_name' => 'api',
            ],
            [
                'name' => [
                    'ar' => 'مهندس موقع',
                    'en' => 'Site Engineer',
                ],
            ]
        );

        $permissions = array_merge(
            $generator->handle($models, $methods, 'site-engineer'),
            $generator->handle(['projects'], ['show'], 'site-engineer')
        );

        $role->syncPermissions($permissions);
    }
}