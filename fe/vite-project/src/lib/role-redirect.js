import {
  BuildingOfficeIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
  ClockIcon,
  FolderIcon,
  HomeIcon,
  ListBulletIcon,
  TagIcon,
  UserCircleIcon,
  UsersIcon,
  ChartBarIcon,
} from '@heroicons/react/24/outline';

/** Every role lands on the dashboard after login now (plan mục 9.1). */
export function roleHomePath(employee) {
  return employee ? '/dashboard' : '/login';
}

const NAV = {
  dashboard: { to: '/dashboard', label: 'Trang chủ', icon: HomeIcon },
  departments: { to: '/departments', label: 'Phòng ban', icon: BuildingOfficeIcon },
  employees: { to: '/employees', label: 'Nhân viên', icon: UsersIcon },
  levels: { to: '/levels', label: 'Cấp bậc', icon: ChartBarIcon },
  projects: { to: '/projects', label: 'Dự án', icon: FolderIcon },
  projectRoles: { to: '/project-roles', label: 'Vai trò dự án', icon: TagIcon },
  leaveRequests: { to: '/leave-requests', label: 'Yêu cầu nghỉ phép', icon: CalendarDaysIcon },
  taskDelayRequests: { to: '/task-delay-requests', label: 'Yêu cầu gia hạn task', icon: ClockIcon },
  approvals: { to: '/approvals', label: 'Phê duyệt', icon: CheckCircleIcon },
  myTasks: { to: '/my-tasks', label: 'Task của tôi', icon: ListBulletIcon },
  myProjects: { to: '/my-projects', label: 'Dự án', icon: FolderIcon },
  profile: { to: '/me', label: 'Hồ sơ', icon: UserCircleIcon },
};

/**
 * @return {{to: string, label: string, icon: import('react').ComponentType}[]}
 */
export function sidebarNavItems(employee) {
  if (!employee) {
    return [];
  }

  switch (employee.position) {
    case 'admin':
      return [
        NAV.dashboard,
        NAV.departments,
        NAV.employees,
        NAV.levels,
        NAV.projects,
        NAV.projectRoles,
        NAV.leaveRequests,
        NAV.taskDelayRequests,
        NAV.approvals,
      ];
    case 'manager':
      return [
        NAV.dashboard,
        {
          to: employee.department_slug ? `/departments/${employee.department_slug}` : '/dashboard',
          label: 'Phòng ban của tôi',
          icon: BuildingOfficeIcon,
        },
        NAV.employees,
        NAV.levels,
        NAV.projects,
        NAV.projectRoles,
        NAV.leaveRequests,
        NAV.taskDelayRequests,
        NAV.approvals,
      ];
    default:
      return [NAV.dashboard, NAV.myTasks, NAV.myProjects, NAV.leaveRequests, NAV.taskDelayRequests, NAV.approvals];
  }
}
