<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles=['administrator','childcare_centre','educator','professional'];
        $permissions=[
            'manage users','manage centres','manage educators','manage jobs',
            'manage professionals','manage claims','manage inquiries','manage content','view reports'
        ];
        foreach($permissions as $p) Permission::findOrCreate($p);
        foreach($roles as $r) Role::findOrCreate($r);
        Role::findByName('administrator')->syncPermissions(Permission::all());
    }
}
