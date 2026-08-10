export function roleHomePath(employee) {
  if (!employee) {
    return '/login';
  }

  switch (employee.position) {
    case 'admin':
      return '/departments';
    case 'manager':
      return employee.department_slug ? `/departments/${employee.department_slug}` : '/me';
    default:
      return '/me';
  }
}

const NAV = {
  departments: { to: '/departments', label: 'Phòng ban' },
  employees: { to: '/employees', label: 'Nhân viên' },
  levels: { to: '/levels', label: 'Cấp bậc' },
  projects: { to: '/projects', label: 'Dự án' },
  projectRoles: { to: '/project-roles', label: 'Vai trò dự án' },
  leaveRequests: { to: '/leave-requests', label: 'Đơn nghỉ phép' },
  profile: { to: '/me', label: 'Hồ sơ' },
};

/**
 * @return {{to: string, label: string}[]}
 */
export function headerNavItems(employee) {
  if (!employee) {
    return [];
  }

  switch (employee.position) {
    case 'admin':
      return [
        NAV.departments,
        NAV.employees,
        NAV.levels,
        NAV.projects,
        NAV.projectRoles,
        NAV.leaveRequests,
      ];
    case 'manager':
      return [
        { to: roleHomePath(employee), label: 'Phòng ban của tôi' },
        NAV.employees,
        NAV.levels,
        NAV.projects,
        NAV.projectRoles,
        NAV.leaveRequests,
      ];
    default:
      return [NAV.profile, NAV.leaveRequests];
  }
}
