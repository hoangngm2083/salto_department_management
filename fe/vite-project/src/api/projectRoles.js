import http from '../lib/http';

export async function listProjectRoles(params) {
  const { data } = await http.get('/project-roles', { params });

  return data.data;
}

export async function getProjectRole(slug) {
  const { data } = await http.get(`/project-roles/${slug}`);

  return data.data;
}

export async function createProjectRole(payload) {
  const { data } = await http.post('/project-roles', payload);

  return data.data;
}

export async function updateProjectRole(slug, payload) {
  const { data } = await http.put(`/project-roles/${slug}`, payload);

  return data.data;
}

export async function deleteProjectRole(slug) {
  await http.delete(`/project-roles/${slug}`);
}
