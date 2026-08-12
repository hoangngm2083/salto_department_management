/**
 * Merges work-history rows (project assignments, from `getEmployeeWorkHistory`)
 * with managed-project rows (PM-ship, from `getEmployeeManagedProjects`) into
 * one list shaped like a work-history row, adding a synthetic "Project
 * Manager" role period for any project the employee manages. Both endpoints
 * are already scoped to "currently active" (assignments via `?active=1`,
 * managers always via `activeManagers`), so every row this returns is
 * current - no further end_date filtering needed by the caller.
 */
export function mergeMyProjects(assignedProjects, managedProjects, employeeId) {
  const bySlug = new Map(
    assignedProjects.map((project) => [project.project_slug, { ...project, roles: [...project.roles] }])
  );

  for (const project of managedProjects) {
    const pm = project.managers.find((manager) => manager.employee_id === employeeId);

    if (!pm) {
      continue;
    }

    const pmRole = { role: 'Project Manager', start_date: pm.start_date, end_date: pm.end_date };
    const existing = bySlug.get(project.slug);

    if (existing) {
      existing.roles.push(pmRole);
      existing.start_date = existing.start_date < pm.start_date ? existing.start_date : pm.start_date;
      existing.end_date =
        existing.end_date && pm.end_date ? (existing.end_date > pm.end_date ? existing.end_date : pm.end_date) : null;
    } else {
      bySlug.set(project.slug, {
        project_id: project.id,
        project: project.name,
        project_slug: project.slug,
        project_status: project.status,
        project_end_date: project.end_date,
        start_date: pm.start_date,
        end_date: pm.end_date,
        roles: [pmRole],
      });
    }
  }

  return [...bySlug.values()];
}
