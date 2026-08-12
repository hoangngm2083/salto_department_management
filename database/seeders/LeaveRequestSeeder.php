<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Services\LeaveRequestService;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Goes through the real service (not a bare factory) so `LeaveRequestSubmitted`/
     * `LeaveRequestReviewed` fire and the notifications bell has real data to show.
     * 1 request left Pending on purpose - the live "manager duyệt" demo moment.
     */
    public function run(LeaveRequestService $leaveRequestService): void
    {
        $emp = fn (string $email): Employee => Employee::where('email', $email)->sole();

        $duc = $emp('employee@example.com');
        $mai = $emp('manager@example.com');
        $thu = $emp('dev04@example.com');
        $nam = $emp('dev05@example.com');
        $kim = $emp('dev13@example.com');

        // Đang chờ duyệt - demo trực tiếp: đăng nhập manager@example.com để duyệt.
        $leaveRequestService->create($duc, [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Về quê giải quyết việc gia đình.',
        ]);

        // Lịch sử: đã duyệt.
        $approved = $leaveRequestService->create($thu, [
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(18)->toDateString(),
            'reason' => 'Nghỉ phép năm.',
        ]);
        $leaveRequestService->updateStatus($mai, $approved, [
            'status' => 'approved',
            'review_note' => 'Đã sắp xếp người phụ trách thay thế.',
        ]);

        // Lịch sử: đã từ chối.
        $rejected = $leaveRequestService->create($nam, [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Nghỉ để đi du lịch cùng gia đình.',
        ]);
        $leaveRequestService->updateStatus($mai, $rejected, [
            'status' => 'rejected',
            'review_note' => 'Trùng giai đoạn UAT của dự án, vui lòng dời lịch.',
        ]);

        // Phòng Kinh doanh - demo phân quyền theo department (chỉ sales-manager/admin thấy).
        $leaveRequestService->create($kim, [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Khám sức khoẻ định kỳ.',
        ]);
    }
}
