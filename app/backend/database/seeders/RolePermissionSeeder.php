<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-users',
            'manage-courses',
            'view-reports',
            'create-courses',
            'edit-courses',
            'delete-courses',
            'create-lessons',
            'edit-lessons',
            'create-assignments',
            'grade-submissions',
            'view-courses',
            'view-lessons',
            'view-assignments',
            'enroll-courses',
            'submit-assignments',
            'view-own-grades',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions($permissions);

        $instructor = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'api']);
        $instructor->syncPermissions([
            'create-courses', 'edit-courses',
            'create-lessons', 'edit-lessons',
            'create-assignments', 'grade-submissions',
            'view-courses', 'view-lessons', 'view-assignments',
        ]);

        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
        $student->syncPermissions([
            'view-courses', 'view-lessons', 'view-assignments',
            'enroll-courses', 'submit-assignments', 'view-own-grades',
        ]);
    }
}
