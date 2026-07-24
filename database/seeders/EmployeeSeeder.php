<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::all();

        if ($departments->isEmpty()) {
            $departments = Department::factory(5)->create();
        }

        // Create an admin/manager employee account for testing
        Employee::factory()->create([
            'department_id' => $departments->first()->id,
            'name' => 'Admin Employee',
            'email' => 'admin@example.com',
            'position' => 'admin',
        ]);

        // Create 20 random employees distributed across departments
        Employee::factory(20)->recycle($departments)->create();
    }
}
