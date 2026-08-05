import http from '../lib/http';

export async function listNotifications(params) {
  const { data } = await http.get('/notifications', { params });

  return data.data;
}

export async function updateNotificationStatus(id, status) {
  const { data } = await http.patch(`/notifications/${id}`, { status });

  return data.data;
}

export async function markNotificationsAsRead(ids) {
  const { data } = await http.patch('/notifications', { status: 'read', ids });

  return data.data;
}
