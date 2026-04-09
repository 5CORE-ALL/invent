<?php

namespace Database\Seeders;

use App\Models\ResourceDepartment;
use App\Models\ResourceTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ResourceMasterDepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['slug' => 'hr', 'name' => 'HR', 'sort_order' => 10],
            ['slug' => 'accounts', 'name' => 'Accounts', 'sort_order' => 20],
            ['slug' => 'warehouse', 'name' => 'Warehouse', 'sort_order' => 30],
            ['slug' => 'purchase', 'name' => 'Purchase', 'sort_order' => 40],
            ['slug' => 'sales', 'name' => 'Sales', 'sort_order' => 50],
            ['slug' => 'marketing', 'name' => 'Marketing', 'sort_order' => 60],
            ['slug' => 'software', 'name' => 'Software', 'sort_order' => 70],
            ['slug' => 'management', 'name' => 'Management', 'sort_order' => 80],
        ];

        foreach ($defs as $d) {
            ResourceDepartment::firstOrCreate(
                ['slug' => $d['slug']],
                ['name' => $d['name'], 'sort_order' => $d['sort_order']]
            );
        }

        $tags = ['Policies', 'SOP', 'Forms', 'Onboarding', 'Compliance', 'Safety'];
        foreach (ResourceDepartment::all() as $dept) {
            foreach ($tags as $t) {
                $slug = Str::slug($t.'-'.$dept->slug);
                ResourceTag::firstOrCreate(
                    ['slug' => $slug, 'department_id' => $dept->id],
                    ['tag_name' => $t]
                );
            }
        }
    }
}
