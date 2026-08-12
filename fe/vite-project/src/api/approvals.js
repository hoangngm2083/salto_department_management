import http from '../lib/http';

export async function listApprovals(params) {
  const { data } = await http.get('/approvals', { params });

  return data.data;
}

export async function getApproval(id) {
  const { data } = await http.get(`/approvals/${id}`);

  return data.data;
}

export async function updateApproval(id, payload) {
  const { data } = await http.patch(`/approvals/${id}`, payload);

  return data.data;
}
