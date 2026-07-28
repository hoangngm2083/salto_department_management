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
