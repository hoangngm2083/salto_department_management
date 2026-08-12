<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Level;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Roster keyed by email so other seeders (Project/Task/LeaveRequest/ApprovalDemo)
     * can look employees back up without re-declaring names. `manager` references
     * another row's email within this same roster - resolved in a second pass since
     * PHP evaluates the array top-to-bottom and later rows need earlier ids.
     *
     * Every `position: employee` row's `manager` points at `hr-manager@example.com`
     * rather than its own department's manager - direct management is an HR function
     * in this system (config('departments.hr_slug'), enforced at UpsertEmployeeRequest),
     * not the employee's own functional department head.
     */
    public function run(): void
    {
        $departments = Department::query()->pluck('id', 'slug');
        $levels = Level::query()->pluck('id', 'slug');

        $roster = [
            ['name' => 'Quản trị viên Hệ thống', 'email' => 'admin@example.com', 'department' => 'phong-cong-nghe', 'position' => 'admin', 'level' => 'principal', 'manager' => null, 'status' => 'active'],

            // HR declared first - every other department's employee rows below reference
            // hr-manager@example.com as their manager, and $managerIds is only populated by
            // rows already processed earlier in this top-to-bottom pass.
            ['name' => 'Nguyễn Thị Lan', 'email' => 'hr-manager@example.com', 'department' => 'phong-nhan-su', 'position' => 'manager', 'level' => 'senior', 'manager' => null, 'status' => 'active'],
            ['name' => 'Đinh Văn Phúc', 'email' => 'dev16@example.com', 'department' => 'phong-nhan-su', 'position' => 'employee', 'level' => 'fresher', 'manager' => 'hr-manager@example.com', 'status' => 'active'],

            ['name' => 'Trần Thị Mai', 'email' => 'manager@example.com', 'department' => 'phong-cong-nghe', 'position' => 'manager', 'level' => 'senior', 'manager' => null, 'status' => 'active'],
            ['name' => 'Lê Văn Đức', 'email' => 'employee@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'middle', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Đặng Thị Thu', 'email' => 'dev04@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Hoàng Văn Nam', 'email' => 'dev05@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'middle', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Ngô Thị Vy', 'email' => 'dev06@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Vũ Thị Ngọc', 'email' => 'dev07@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'middle', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Nguyễn Thị Hương', 'email' => 'dev08@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'senior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Bùi Văn Khoa', 'email' => 'dev09@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'senior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Đỗ Thị Hạnh', 'email' => 'dev10@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Phan Văn Long', 'email' => 'dev11@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'fresher', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Trịnh Văn Sơn', 'email' => 'dev12@example.com', 'department' => 'phong-cong-nghe', 'position' => 'employee', 'level' => 'intern', 'manager' => 'hr-manager@example.com', 'status' => 'inactive'],

            ['name' => 'Phạm Quốc Huy', 'email' => 'sales-manager@example.com', 'department' => 'phong-kinh-doanh', 'position' => 'manager', 'level' => 'senior', 'manager' => null, 'status' => 'active'],
            ['name' => 'Lý Thị Kim', 'email' => 'dev13@example.com', 'department' => 'phong-kinh-doanh', 'position' => 'employee', 'level' => 'middle', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Trương Văn Tài', 'email' => 'dev14@example.com', 'department' => 'phong-kinh-doanh', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Vương Thị Thảo', 'email' => 'dev15@example.com', 'department' => 'phong-kinh-doanh', 'position' => 'employee', 'level' => 'middle', 'manager' => 'hr-manager@example.com', 'status' => 'active'],

            ['name' => 'Đỗ Minh Tuấn', 'email' => 'marketing-manager@example.com', 'department' => 'phong-marketing', 'position' => 'manager', 'level' => 'middle', 'manager' => null, 'status' => 'active'],
            ['name' => 'Hồ Thị Yến', 'email' => 'dev17@example.com', 'department' => 'phong-marketing', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],
            ['name' => 'Lâm Văn Khải', 'email' => 'dev18@example.com', 'department' => 'phong-marketing', 'position' => 'employee', 'level' => 'junior', 'manager' => 'hr-manager@example.com', 'status' => 'active'],

            ['name' => 'Cao Văn Đạt', 'email' => 'dev19@example.com', 'department' => 'phong-van-hanh', 'position' => 'employee', 'level' => 'middle', 'manager' => null, 'status' => 'resigned'],
        ];

        $managerIds = [];

        foreach ($roster as $row) {
            $employee = Employee::factory()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'department_id' => $departments[$row['department']],
                'birthday' => now()->subYears(random_int(22, 45))->subDays(random_int(0, 365))->toDateString(),
                'position' => $row['position'],
                'current_level_id' => $levels[$row['level']],
                'manager_employee_id' => $row['manager'] !== null ? $managerIds[$row['manager']] : null,
                'status' => $row['status'],
            ]);

            $managerIds[$row['email']] = $employee->id;
        }
    }
}
