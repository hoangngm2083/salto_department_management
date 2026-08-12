<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Services\ApprovalRequestService;
use App\Services\LeaveRequestService;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Goes through the real services (not bare factories) so approval_steps/notifications
     * end up in the exact shape the live app produces - mirrors ApprovalDemoSeeder's own
     * doc comment. 1 request left Pending on purpose - the live "PM/HR duyệt" demo moment:
     * log in as manager@example.com to approve its PM step first.
     *
     * Exercises both step-count paths the workflow can produce: 3 cases below have an
     * active project assignment (2-step PM -> HR), 1 case (Kim, Phòng Kinh doanh) has none
     * (1-step HR-only, PM step skipped by LeaveRequestApprovalWorkflow).
     */
    public function run(LeaveRequestService $leaveRequestService, ApprovalRequestService $approvalRequestService): void
    {
        $emp = fn (string $email): Employee => Employee::where('email', $email)->sole();
        $activeProjectIdOf = fn (Employee $employee): ?int => ProjectAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->value('project_id');

        $duc = $emp('employee@example.com');
        $mai = $emp('manager@example.com');
        $thu = $emp('dev04@example.com');
        $nam = $emp('dev05@example.com');
        $kim = $emp('dev13@example.com');
        $hrManager = $emp('hr-manager@example.com');

        // Đang chờ duyệt - demo trực tiếp: đăng nhập manager@example.com để duyệt bước PM,
        // sau đó hr-manager@example.com duyệt bước HR.
        $leaveRequestService->create($duc, [
            'project_id' => $activeProjectIdOf($duc),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Về quê giải quyết việc gia đình.',
        ]);

        // Lịch sử: đã duyệt cả 2 bước (PM -> HR).
        $approved = $leaveRequestService->create($thu, [
            'project_id' => $activeProjectIdOf($thu),
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(18)->toDateString(),
            'reason' => 'Nghỉ phép năm.',
        ]);
        $approvalAfterPm = $approvalRequestService->approve($mai, $approved->approvalRequest, 'Đã sắp xếp người phụ trách thay thế.');
        $approvalRequestService->approve($hrManager, $approvalAfterPm, 'Đủ ngày phép, đồng ý.');

        // Lịch sử: PM từ chối ngay bước đầu - bước HR không bao giờ được kích hoạt.
        $rejected = $leaveRequestService->create($nam, [
            'project_id' => $activeProjectIdOf($nam),
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Nghỉ để đi du lịch cùng gia đình.',
        ]);
        $approvalRequestService->reject($mai, $rejected->approvalRequest, 'Trùng giai đoạn UAT của dự án, vui lòng dời lịch.');

        // Lịch sử: Lý Thị Kim (Phòng Kinh doanh) không có project assignment active - workflow
        // chỉ có đúng 1 bước HR, minh hoạ nhánh "bỏ qua bước PM".
        $noProjectApproved = $leaveRequestService->create($kim, [
            'project_id' => $activeProjectIdOf($kim),
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Khám sức khoẻ định kỳ.',
        ]);
        $approvalRequestService->approve($hrManager, $noProjectApproved->approvalRequest, 'Đồng ý.');
    }
}
