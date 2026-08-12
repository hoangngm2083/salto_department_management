import http from '../lib/http';

export async function listProjects(params) {
  const { data } = await http.get('/projects', { params });

  return data.data;
}

export async function getProject(slug) {
  const { data } = await http.get(`/projects/${slug}`);

  return data.data;
}

export async function createProject(payload) {
  const { data } = await http.post('/projects', payload);

  return data.data;
}

export async function updateProject(slug, payload) {
  const { data } = await http.put(`/projects/${slug}`, payload);

  return data.data;
}

export async function deleteProject(slug) {
  await http.delete(`/projects/${slug}`);
}

export async function addProjectManager(slug, employeeId) {
  const { data } = await http.post(`/projects/${slug}/managers`, { employee_id: employeeId });

  return data.data;
}

export async function removeProjectManager(slug, projectManagerId, replacementEmployeeId) {
  const { data } = await http.delete(`/projects/${slug}/managers/${projectManagerId}`, {
    data: replacementEmployeeId ? { replacement_employee_id: replacementEmployeeId } : undefined,
  });

  return data.data;
}
