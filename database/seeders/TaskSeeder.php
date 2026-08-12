<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * ERP covers all 5 TaskStatus values plus 1 overdue task; Mobile adds a
     * second overdue task (feeds the admin "Dự án cần chú ý" widget); Ecommerce
     * gets a couple of Done tasks for a finished-project feel. Task delay
     * requests: 1 left Pending on purpose for the live "PM duyệt" demo moment.
     */
    public function run(): void
    {
        $emp = fn (string $email): Employee => Employee::where('email', $email)->sole();

        $mai = $emp('manager@example.com');
        $duc = $emp('employee@example.com');
        $thu = $emp('dev04@example.com');
        $nam = $emp('dev05@example.com');
        $khoa = $emp('dev09@example.com');
        $huong = $emp('dev08@example.com');
        $ngoc = $emp('dev07@example.com');
        $vy = $emp('dev06@example.com');
        $hanh = $emp('dev10@example.com');
        $long = $emp('dev11@example.com');

        $erp = Project::where('slug', 'erp-noi-bo')->sole();
        $mobile = Project::where('slug', 'mobile-ban-hang')->sole();
        $ecommerce = Project::where('slug', 'website-tmdt')->sole();

        // ---- ERP ----
        $t1 = $erp->tasks()->create([
            'assigned_to' => $duc->id, 'created_by' => $mai->id, 'reviewed_by' => $mai->id,
            'title' => 'Thiết kế schema module Kế toán', 'status' => 'done',
            'due_date' => now()->subDays(10)->toDateString(), 'reviewed_at' => now()->subDays(9), 'review_note' => 'Đạt yêu cầu, đã merge.',
        ]);
        $t1->comments()->create(['employee_id' => $mai->id, 'body' => 'Đã kiểm tra, đạt yêu cầu.', 'task_status' => 'done']);

        $t2 = $erp->tasks()->create([
            'assigned_to' => $khoa->id, 'created_by' => $mai->id, 'reviewed_by' => $mai->id,
            'title' => 'Xây dựng API xác thực SSO', 'status' => 'done',
            'due_date' => now()->subDays(6)->toDateString(), 'reviewed_at' => now()->subDays(5),
        ]);

        $t3 = $erp->tasks()->create([
            'assigned_to' => $duc->id, 'created_by' => $mai->id,
            'title' => 'Tích hợp cổng thanh toán nội bộ', 'status' => 'in_review',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $t3->comments()->create(['employee_id' => $duc->id, 'body' => 'Đã code xong, chờ PM duyệt.', 'task_status' => 'in_review']);
        $t3->comments()->create(['employee_id' => $mai->id, 'body' => 'Đang review, cho anh 1 ngày nhé.', 'task_status' => null]);

        $erp->tasks()->create([
            'assigned_to' => $thu->id, 'created_by' => $mai->id,
            'title' => 'Xây dựng giao diện Dashboard báo cáo', 'status' => 'in_progress',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $t5 = $erp->tasks()->create([
            'assigned_to' => $nam->id, 'created_by' => $mai->id,
            'title' => 'Viết test case module Kho vận', 'status' => 'in_progress',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $erp->tasks()->create([
            'assigned_to' => null, 'created_by' => $mai->id,
            'title' => 'Chuẩn hoá tài liệu API', 'status' => 'todo',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $erp->tasks()->create([
            'assigned_to' => $duc->id, 'created_by' => $mai->id,
            'title' => 'Refactor module thông báo', 'status' => 'todo',
            'due_date' => now()->addDays(20)->toDateString(),
        ]);

        $erp->tasks()->create([
            'assigned_to' => $thu->id, 'created_by' => $mai->id,
            'title' => 'Nâng cấp thư viện UI cũ', 'status' => 'cancelled',
            'due_date' => null,
        ]);

        // Task delay request: 1 đang chờ duyệt (demo trực tiếp), 1 đã duyệt trước đó (lịch sử)
        $t5->delayRequests()->create([
            'requested_by' => $nam->id,
            'current_due_date' => $t5->due_date,
            'requested_due_date' => now()->addDays(12)->toDateString(),
            'reason' => 'Xung đột nguồn lực với dự án khác, cần thêm thời gian test.',
            'status' => 'pending',
        ]);

        $t2->delayRequests()->create([
            'requested_by' => $khoa->id,
            'current_due_date' => now()->subDays(10)->toDateString(),
            'requested_due_date' => now()->subDays(6)->toDateString(),
            'reason' => 'Chờ bên thứ 3 cấp API key.',
            'status' => 'approved',
            'reviewed_by' => $mai->id,
            'reviewed_at' => now()->subDays(9),
            'review_note' => 'Đồng ý, không ảnh hưởng tiến độ chung.',
        ]);

        // ---- Mobile ----
        $mobile->tasks()->create([
            'assigned_to' => null, 'created_by' => $huong->id,
            'title' => 'Thiết kế UI màn hình giỏ hàng', 'status' => 'todo',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $mobile->tasks()->create([
            'assigned_to' => $ngoc->id, 'created_by' => $huong->id,
            'title' => 'Tích hợp SDK thanh toán Momo', 'status' => 'in_progress',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $mobile->tasks()->create([
            'assigned_to' => $vy->id, 'created_by' => $huong->id,
            'title' => 'Kiểm thử luồng đăng ký tài khoản', 'status' => 'in_review',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $mobile->tasks()->create([
            'assigned_to' => $huong->id, 'created_by' => $huong->id, 'reviewed_by' => $huong->id,
            'title' => 'Nghiên cứu yêu cầu nghiệp vụ', 'status' => 'done',
            'due_date' => now()->subDays(20)->toDateString(), 'reviewed_at' => now()->subDays(19),
        ]);

        // ---- Ecommerce (đã hoàn thành) ----
        $ecommerce->tasks()->create([
            'assigned_to' => $hanh->id, 'created_by' => $mai->id, 'reviewed_by' => $mai->id,
            'title' => 'Đặc tả nghiệp vụ luồng thanh toán', 'status' => 'done',
            'due_date' => now()->subMonths(2)->toDateString(), 'reviewed_at' => now()->subMonths(2),
        ]);

        $ecommerce->tasks()->create([
            'assigned_to' => $long->id, 'created_by' => $mai->id, 'reviewed_by' => $mai->id,
            'title' => 'Xây dựng API giỏ hàng', 'status' => 'done',
            'due_date' => now()->subMonths(2)->toDateString(), 'reviewed_at' => now()->subMonths(2),
        ]);
    }
}
