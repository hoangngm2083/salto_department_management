<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use App\Services\ApprovalRequestService;
use App\Services\RoleChangeRequestService;
use Illuminate\Database\Seeder;

class ApprovalDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Exercises the Approval Engine through its real services (not factories) so
     * approval_steps/approval_actions/notifications all end up in the exact shape
     * the live app produces. Leaves request #1 Pending on purpose - the headline
     * live-demo moment: log in as manager@example.com and approve it from /approvals.
     */
    public function run(RoleChangeRequestService $roleChangeRequestService, ApprovalRequestService $approvalRequestService): void
    {
        $emp = fn (string $email): Employee => Employee::where('email', $email)->sole();
        $roleId = fn (string $slug): int => ProjectRole::where('slug', $slug)->sole()->id;
        $assignmentOf = fn (string $employeeEmail, string $projectSlug): ProjectAssignment => ProjectAssignment::query()
            ->whereRelation('employee', 'email', $employeeEmail)
            ->whereRelation('project', 'slug', $projectSlug)
            ->sole();

        $duc = $emp('employee@example.com');
        $mai = $emp('manager@example.com');
        $nam = $emp('dev05@example.com');
        $ngoc = $emp('dev07@example.com');
        $huong = $emp('dev08@example.com');

        // 1) ĐANG CHỜ DUYỆT - demo trực tiếp: Lê Văn Đức xin thêm vai trò Tech Lead trên ERP.
        $roleChangeRequestService->create($duc, [
            'project_assignment_id' => $assignmentOf('employee@example.com', 'erp-noi-bo')->id,
            'change_mode' => 'add',
            'to_project_role_id' => $roleId('tech-lead'),
            'reason' => 'Đã dẫn dắt kỹ thuật cho module thanh toán 2 tháng qua, đề xuất ghi nhận chính thức vai trò Tech Lead.',
        ]);

        // 2) ĐÃ DUYỆT + ÁP DỤNG (lịch sử) - Vũ Thị Ngọc: Frontend -> Tech Lead trên Mobile.
        $applied = $roleChangeRequestService->create($ngoc, [
            'project_assignment_id' => $assignmentOf('dev07@example.com', 'mobile-ban-hang')->id,
            'change_mode' => 'replace',
            'from_project_role_id' => $roleId('frontend'),
            'to_project_role_id' => $roleId('tech-lead'),
            'reason' => 'Nhận thêm trách nhiệm điều phối kỹ thuật cho module thanh toán Momo.',
        ]);
        $approvalRequestService->approve($huong, $applied->approvalRequest, 'Đồng ý, bạn đã chứng minh năng lực qua sprint vừa rồi.');

        // 3) ĐÃ TỪ CHỐI (lịch sử) - Hoàng Văn Nam muốn rời vai trò QA trên ERP.
        $rejected = $roleChangeRequestService->create($nam, [
            'project_assignment_id' => $assignmentOf('dev05@example.com', 'erp-noi-bo')->id,
            'change_mode' => 'remove',
            'from_project_role_id' => $roleId('qa'),
            'reason' => 'Muốn tập trung hẳn sang mảng Backend.',
        ]);
        $approvalRequestService->reject($mai, $rejected->approvalRequest, 'Vẫn cần QA cho giai đoạn UAT, sẽ xem xét lại sau khi dự án kết thúc.');
    }
}
