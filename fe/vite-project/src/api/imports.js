import http from '../lib/http';

export async function createImport(type, file, config) {
  const formData = new FormData();
  formData.append('type', type);
  formData.append('file', file);

  const { data } = await http.post('/imports', formData, config);

  return data.data;
}

export async function getImport(importId) {
  const { data } = await http.get(`/imports/${importId}`);

  return data.data;
}
