import http from '../lib/http';

export async function exportDepartments(filters) {
  return downloadCsv('department', filters);
}

export async function exportEmployees(filters) {
  return downloadCsv('employee', filters);
}

async function downloadCsv(type, filters) {
  const response = await http.get('/exports', {
    params: { type, ...filters },
    responseType: 'blob',
  });

  const blobUrl = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = blobUrl;
  link.download = `${type}s_export_${Date.now()}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
}
