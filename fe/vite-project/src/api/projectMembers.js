import http from '../lib/http';

export async function searchProjectMembers(slug, params) {
  const { data } = await http.get(`/projects/${slug}/members`, { params });

  return data.data;
}
