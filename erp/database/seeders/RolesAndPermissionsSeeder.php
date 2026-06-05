<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Roles
        $admin    = Role::firstOrCreate(['name' => 'admin']);
        $operator = Role::firstOrCreate(['name' => 'operator']);
        $tech     = Role::firstOrCreate(['name' => 'technician']);

        // Permissões granulares (para uso futuro com políticas mais refinadas)
        $permissions = [
            'view-dashboard',
            'manage-clients',
            'manage-service-orders',
            'view-service-orders',
            'manage-technicians',
            'manage-routes',
            'generate-pdf',
            'view-fiscal',
            'manage-users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin->syncPermissions($permissions);

        $operator->syncPermissions([
            'view-dashboard',
            'manage-clients',
            'manage-service-orders',
            'view-service-orders',
            'manage-routes',
            'generate-pdf',
            'view-fiscal',
        ]);

        $tech->syncPermissions([
            'view-dashboard',
            'view-service-orders',
            'generate-pdf',
        ]);
    }
}
