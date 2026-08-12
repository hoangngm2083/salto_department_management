import http from '../lib/http';

export async function listTaskComments(taskId, params) {
  const { data } = await http.get(`/tasks/${taskId}/comments`, { params });

  return data.data;
}

export async function createTaskComment(taskId, payload) {
  const { data } = await http.post(`/tasks/${taskId}/comments`, payload);

  return data.data;
}
