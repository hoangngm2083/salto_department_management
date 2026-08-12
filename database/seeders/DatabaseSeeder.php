<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a coherent demo dataset (departments,
     * levels, projects, tasks, leave/role-change requests...). Queue forced to
     * `sync` for this run only, so Approval Engine / Leave Request notifications
     * are actually written to the `notifications` table instead of sitting
     * unprocessed in `jobs` (both ShouldQueue listeners default to `database`).
     */
    public function run(): void
    {
        config(['queue.default' => 'sync']);

        $this->call([
            DepartmentSeeder::class,
            LevelSeeder::class,
            ProjectRoleSeeder::class,
            EmployeeSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
            LeaveRequestSeeder::class,
            ApprovalDemoSeeder::class,
        ]);
    }
}
