import http from '../lib/http';

export async function listProjectTasks(slug, params) {
  const { data } = await http.get(`/projects/${slug}/tasks`, { params });

  return data.data;
}

export async function getTask(id) {
  const { data } = await http.get(`/tasks/${id}`);

  return data.data;
}

export async function createTask(slug, payload) {
  const { data } = await http.post(`/projects/${slug}/tasks`, payload);

  return data.data;
}

export async function updateTaskStatus(id, payload) {
  const { data } = await http.patch(`/tasks/${id}`, payload);

  return data.data;
}
