import http from '../lib/http';

export async function listTaskDelayRequests(params) {
  const { data } = await http.get('/task-delay-requests', { params });

  return data.data;
}

export async function createTaskDelayRequest(payload) {
  const { data } = await http.post('/task-delay-requests', payload);

  return data.data;
}

export async function updateTaskDelayRequestStatus(id, payload) {
  const { data } = await http.patch(`/task-delay-requests/${id}`, payload);

  return data.data;
}
