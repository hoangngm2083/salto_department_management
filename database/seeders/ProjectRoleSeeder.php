<?php

namespace Database\Seeders;

use App\Models\ProjectRole;
use Illuminate\Database\Seeder;

class ProjectRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Frontend', 'slug' => 'frontend'],
            ['name' => 'Backend', 'slug' => 'backend'],
            ['name' => 'DevOps', 'slug' => 'devops'],
            ['name' => 'QA', 'slug' => 'qa'],
            ['name' => 'QC', 'slug' => 'qc'],
            ['name' => 'BrSE', 'slug' => 'brse'],
            ['name' => 'BA', 'slug' => 'ba'],
            ['name' => 'Tech Lead', 'slug' => 'tech-lead'],
            ['name' => 'Project Manager', 'slug' => 'project-manager'],
        ];

        foreach ($roles as $role) {
            ProjectRole::create([...$role, 'description' => null, 'status' => 'active']);
        }
    }
}
