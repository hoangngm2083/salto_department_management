import http from '../lib/http';

export async function listEmployees(params) {
  const { data } = await http.get('/employees', { params });

  return data.data;
}

export async function getEmployee(id) {
  const { data } = await http.get(`/employees/${id}`);

  return data.data;
}

export async function createEmployee(payload) {
  const { data } = await http.post('/employees', payload);

  return data.data;
}

export async function updateEmployee(id, payload) {
  const { data } = await http.put(`/employees/${id}`, payload);

  return data.data;
}

export async function deleteEmployee(id) {
  await http.delete(`/employees/${id}`);
}

export async function getEmployeeWorkHistory(id) {
  const { data } = await http.get(`/employees/${id}/projects`);

  return data.data;
}

export async function getEmployeeTasks(id, params) {
  const { data } = await http.get(`/employees/${id}/tasks`, { params });

  return data.data;
}
