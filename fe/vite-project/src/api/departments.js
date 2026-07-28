import http from '../lib/http';

export async function listDepartments(params) {
  const { data } = await http.get('/departments', { params });

  return data.data;
}

export async function getDepartment(slug) {
  const { data } = await http.get(`/departments/${slug}`);

  return data.data;
}
