<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Phòng Công nghệ', 'slug' => 'phong-cong-nghe', 'description' => 'Phát triển và vận hành toàn bộ sản phẩm phần mềm của công ty.', 'status' => 'active'],
            ['name' => 'Phòng Kinh doanh', 'slug' => 'phong-kinh-doanh', 'description' => 'Phát triển khách hàng và ký kết hợp đồng.', 'status' => 'active'],
            ['name' => 'Phòng Nhân sự', 'slug' => 'phong-nhan-su', 'description' => 'Tuyển dụng, đào tạo và quản lý nhân sự toàn công ty.', 'status' => 'active'],
            ['name' => 'Phòng Marketing', 'slug' => 'phong-marketing', 'description' => 'Truyền thông thương hiệu và phát triển thị trường.', 'status' => 'active'],
            ['name' => 'Phòng Vận hành', 'slug' => 'phong-van-hanh', 'description' => 'Phòng ban cũ, đã ngừng hoạt động sau đợt tái cơ cấu.', 'status' => 'inactive'],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
