import http from '../lib/http';

export async function createRoleChangeRequest(payload) {
  const { data } = await http.post('/role-change-requests', payload);

  return data.data;
}

export async function getRoleChangeRequest(id) {
  const { data } = await http.get(`/role-change-requests/${id}`);

  return data.data;
}
