<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use App\Services\EmployeeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Covers all 4 ProjectStatus values and all 4 ProjectAssignmentStatus values,
     * and makes 2 of the 5 project managers `position=employee` (PM-ness isn't tied
     * to system role - project_management_plan.md mục 9.1) so the demo can show that
     * decoupling directly in the data.
     */
    public function run(EmployeeService $employeeService): void
    {
        $emp = fn (string $email): Employee => Employee::where('email', $email)->sole();
        $role = fn (string $slug): int => ProjectRole::where('slug', $slug)->sole()->id;

        $admin = $emp('admin@example.com');
        $mai = $emp('manager@example.com');
        $duc = $emp('employee@example.com');
        $thu = $emp('dev04@example.com');
        $nam = $emp('dev05@example.com');
        $vy = $emp('dev06@example.com');
        $ngoc = $emp('dev07@example.com');
        $huong = $emp('dev08@example.com');
        $khoa = $emp('dev09@example.com');
        $hanh = $emp('dev10@example.com');
        $long = $emp('dev11@example.com');
        $tai = $emp('dev14@example.com');

        // ---- 1. Hệ thống ERP Nội bộ (Active) - project trung tâm của buổi demo ----
        $erp = Project::create([
            'name' => 'Hệ thống ERP Nội bộ',
            'slug' => 'erp-noi-bo',
            'description' => 'Xây dựng hệ thống quản trị nguồn lực nội bộ: kế toán, kho vận, nhân sự.',
            'status' => 'active',
            'start_date' => now()->subMonths(4)->toDateString(),
            'end_date' => null,
        ]);
        $erp->managers()->create(['employee_id' => $mai->id, 'start_date' => now()->subMonths(4)->toDateString()]);

        $this->assign($erp, $duc, ['backend'], now()->subMonths(3), $mai);
        $this->assign($erp, $thu, ['frontend'], now()->subMonths(2), $mai);
        $this->assign($erp, $nam, ['qa'], now()->subMonths(2), $mai);
        $this->assign($erp, $khoa, ['devops'], now()->subMonths(3), $mai);

        // ---- 2. Ứng dụng Mobile Bán hàng (Active) - PM là "employee", không phải "manager" ----
        $mobile = Project::create([
            'name' => 'Ứng dụng Mobile Bán hàng',
            'slug' => 'mobile-ban-hang',
            'description' => 'App bán hàng cho nhân viên kinh doanh trên iOS/Android.',
            'status' => 'active',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => null,
        ]);
        $mobile->managers()->create(['employee_id' => $huong->id, 'start_date' => now()->subMonths(2)->toDateString()]);

        $this->assign($mobile, $huong, ['tech-lead'], now()->subMonths(2), $huong);
        $this->assign($mobile, $ngoc, ['frontend'], now()->subMonths(2), $huong);
        $this->assign($mobile, $vy, ['qa'], now()->subMonths(2), $huong);

        // Demo: nhân viên nghỉ việc -> hệ thống tự đóng assignment/role period đang active,
        // đi qua đúng luồng thật (EmployeeService::upsert -> ProjectAssignmentCloserService).
        $employeeService->upsert($admin, ['status' => 'resigned'], $vy);

        // ---- 3. Website Thương mại điện tử (Completed) - lịch sử đã kết thúc ----
        $ecommerce = Project::create([
            'name' => 'Website Thương mại điện tử',
            'slug' => 'website-tmdt',
            'description' => 'Website bán hàng trực tuyến, đã bàn giao và đưa vào vận hành.',
            'status' => 'completed',
            'start_date' => now()->subMonths(10)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);
        $ecommerce->managers()->create(['employee_id' => $mai->id, 'start_date' => now()->subMonths(10)->toDateString()]);

        $this->assignEnded($ecommerce, $hanh, ['ba'], now()->subMonths(9), now()->subMonth(), $mai);
        $this->assignEnded($ecommerce, $long, ['backend'], now()->subMonths(9), now()->subMonth(), $mai);

        // ---- 4. Nâng cấp Hạ tầng Cloud (Planned) - chưa bắt đầu, minh hoạ invariant PM ----
        $cloud = Project::create([
            'name' => 'Nâng cấp Hạ tầng Cloud',
            'slug' => 'nang-cap-cloud',
            'description' => 'Di chuyển hạ tầng sang Kubernetes, chuẩn bị khả năng mở rộng.',
            'status' => 'planned',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => null,
        ]);
        $cloud->managers()->create(['employee_id' => $khoa->id, 'start_date' => now()->toDateString()]);

        // ---- 5. Tích hợp Cổng thanh toán VNPay (Cancelled) ----
        $vnpay = Project::create([
            'name' => 'Tích hợp Cổng thanh toán VNPay',
            'slug' => 'tich-hop-vnpay',
            'description' => 'Dừng triển khai do đổi ưu tiên sang cổng thanh toán nội bộ.',
            'status' => 'cancelled',
            'start_date' => now()->subMonths(5)->toDateString(),
            'end_date' => now()->subMonths(3)->toDateString(),
        ]);
        $vnpay->managers()->create(['employee_id' => $mai->id, 'start_date' => now()->subMonths(5)->toDateString()]);

        $cancelledAssignment = $vnpay->assignments()->create([
            'employee_id' => $tai->id,
            'start_date' => now()->subMonths(5)->toDateString(),
            'end_date' => now()->subMonths(3)->toDateString(),
            'status' => 'cancelled',
            'assigned_by' => $mai->id,
        ]);
        $cancelledAssignment->rolePeriods()->create([
            'project_role_id' => $role('backend'),
            'start_date' => now()->subMonths(5)->toDateString(),
            'end_date' => now()->subMonths(3)->toDateString(),
        ]);
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function assign(Project $project, Employee $employee, array $roleSlugs, Carbon $startDate, Employee $assignedBy): ProjectAssignment
    {
        $assignment = $project->assignments()->create([
            'employee_id' => $employee->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => null,
            'status' => 'active',
            'assigned_by' => $assignedBy->id,
        ]);

        foreach ($roleSlugs as $slug) {
            $assignment->rolePeriods()->create([
                'project_role_id' => ProjectRole::where('slug', $slug)->sole()->id,
                'start_date' => $startDate->toDateString(),
            ]);
        }

        return $assignment;
    }

    /**
     * @param  list<string>  $roleSlugs
     */
    private function assignEnded(Project $project, Employee $employee, array $roleSlugs, Carbon $start, Carbon $end, Employee $assignedBy): ProjectAssignment
    {
        $assignment = $project->assignments()->create([
            'employee_id' => $employee->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'ended',
            'assigned_by' => $assignedBy->id,
        ]);

        foreach ($roleSlugs as $slug) {
            $assignment->rolePeriods()->create([
                'project_role_id' => ProjectRole::where('slug', $slug)->sole()->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        }

        return $assignment;
    }
}
