import http from '../lib/http';

export async function listLevels(params) {
  const { data } = await http.get('/levels', { params });

  return data.data;
}

export async function getLevel(slug) {
  const { data } = await http.get(`/levels/${slug}`);

  return data.data;
}

export async function createLevel(payload) {
  const { data } = await http.post('/levels', payload);

  return data.data;
}

export async function updateLevel(slug, payload) {
  const { data } = await http.put(`/levels/${slug}`, payload);

  return data.data;
}

export async function deleteLevel(slug) {
  await http.delete(`/levels/${slug}`);
}
