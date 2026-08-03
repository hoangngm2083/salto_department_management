import http from '../lib/http';

export async function listLeaveRequests(params) {
  const { data } = await http.get('/leave-requests', { params });

  return data.data;
}

export async function getLeaveRequest(id) {
  const { data } = await http.get(`/leave-requests/${id}`);

  return data.data;
}

export async function createLeaveRequest(payload) {
  const { data } = await http.post('/leave-requests', payload);

  return data.data;
}

export async function updateLeaveRequestStatus(id, payload) {
  const { data } = await http.patch(`/leave-requests/${id}`, payload);

  return data.data;
}
