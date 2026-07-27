Business rules:

Employees
- Admin:
  - Full CRUD.
  - Can assign departments.
  - Can change roles.
- Manager:
  - Can list and view employees in their own department.
  - Can create employees in their own department.
  - Can update employees in their own department.
  - Cannot delete employees.
  - Cannot change employee roles.
- Employee:
  - Can list employees.
  - Can view only their own profile.
  - Can update only their own profile.
  - Cannot change role or department.
  - Cannot create or delete employees.

Departments
- Admin:
  - Full CRUD.
- Manager:
  - Can list departments.
  - Can view any department (or only their own, depending on business requirements).
  - Can update only the department they belong to.
  - Cannot create or delete departments.
- Employee:
  - Read-only access.