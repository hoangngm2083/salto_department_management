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
        { to: '/departments', label: 'Phòng ban' },
        { to: '/employees', label: 'Người dùng' },
      ];
    case 'manager':
      return [
        { to: roleHomePath(employee), label: 'Phòng ban của tôi' },
        { to: '/employees', label: 'Người dùng' },
      ];
    default:
      return [{ to: '/me', label: 'Hồ sơ' }];
  }
}
