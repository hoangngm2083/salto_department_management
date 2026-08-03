Business rules:

Employees
- Admin:
  - Full CRUD.
  - Can assign departments.
  - Can change roles.
  - Import,Export
- Manager:
  - Can view employees in their own department
  - Can create employees in their own department (auto assign department, manager can't change).
  - Can update employees in their own department.
  - Cannot delete employees.
  - Cannot change employee roles, department.
- Employee:
  - Can view only their own profile.
  - Can update only their own profile.
  - Cannot change role or department.
  - Cannot create or delete employees.

Departments
- Admin:
  - Full CRUD.
  - Import,Export
- Manager:
  - Can view only their own department
  - Can update only the department they belong to.
  - Cannot create or delete departments.
- Employee:
  - none