export const APPROVAL_STATUS_LABELS = {
  draft: 'Nháp',
  submitted: 'Chờ duyệt',
  in_review: 'Đang duyệt',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  cancelled: 'Đã hủy',
  applied: 'Đã áp dụng',
  failed: 'Áp dụng lỗi',
};

export const APPROVAL_STATUS_BADGE_CLASSES = {
  draft: 'bg-gray-100 text-gray-600',
  submitted: 'bg-yellow-100 text-yellow-800',
  in_review: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-blue-100 text-blue-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-600',
  applied: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
};

export const APPROVAL_STEP_STATUS_LABELS = {
  pending: 'Chưa tới lượt',
  active: 'Đang chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  skipped: 'Bỏ qua',
};

export const APPROVAL_STEP_STATUS_BADGE_CLASSES = {
  pending: 'bg-gray-100 text-gray-500',
  active: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  skipped: 'bg-gray-100 text-gray-400',
};
