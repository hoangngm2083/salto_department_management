import http from '../lib/http';

export async function createLeaveRequest(payload) {
  const { data } = await http.post('/leave-requests', payload);

  return data.data;
}

export async function getLeaveRequest(id) {
  const { data } = await http.get(`/leave-requests/${id}`);

  return data.data;
}
