import http from '../lib/http';

export async function listProjectAssignments(slug, params) {
  const { data } = await http.get(`/projects/${slug}/assignments`, { params });

  return data.data;
}

export async function createProjectAssignment(slug, payload) {
  const { data } = await http.post(`/projects/${slug}/assignments`, payload);

  return data.data;
}

export async function endProjectAssignment(slug, assignmentId) {
  const { data } = await http.delete(`/projects/${slug}/assignments/${assignmentId}`);

  return data.data;
}

export async function addAssignmentRole(slug, assignmentId, projectRoleId) {
  const { data } = await http.post(`/projects/${slug}/assignments/${assignmentId}/roles`, {
    project_role_id: projectRoleId,
  });

  return data.data;
}

export async function endAssignmentRole(slug, assignmentId, rolePeriodId) {
  const { data } = await http.delete(
    `/projects/${slug}/assignments/${assignmentId}/roles/${rolePeriodId}`
  );

  return data.data;
}
