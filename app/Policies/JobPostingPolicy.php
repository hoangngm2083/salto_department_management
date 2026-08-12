<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;

/**
 * The app has no system "HR" role - only `position` (admin/manager/employee) plus
 * department membership. Recruitment is an HR function, so every ability here is
 * admin (via before()) or a manager who belongs to the HR department
 * (config('departments.hr_slug')), mirroring the same check UpsertEmployeeRequest
 * already applies to `manager_employee_id`.
 */
class JobPostingPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return $this->isHrManager($employee);
    }

    public function view(Employee $employee, JobPosting $jobPosting): bool
    {
        return $this->isHrManager($employee);
    }

    public function create(Employee $employee): bool
    {
        return $this->isHrManager($employee);
    }

    public function update(Employee $employee, JobPosting $jobPosting): bool
    {
        return $this->isHrManager($employee);
    }

    public function delete(Employee $employee, JobPosting $jobPosting): bool
    {
        return $this->isHrManager($employee);
    }

    public function restore(Employee $employee, JobPosting $jobPosting): bool
    {
        return false;
    }

    public function forceDelete(Employee $employee, JobPosting $jobPosting): bool
    {
        return false;
    }

    private function isHrManager(Employee $employee): bool
    {
        return $employee->position === 'manager' && $employee->department_id === $this->hrDepartmentId();
    }

    private function hrDepartmentId(): int
    {
        return Department::query()->where('slug', config('departments.hr_slug'))->value('id') ?? 0;
    }
}
