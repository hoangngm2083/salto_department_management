export const TASK_STATUS_LABELS = {
  todo: 'Chưa làm',
  in_progress: 'Đang làm',
  in_review: 'Chờ duyệt',
  done: 'Hoàn thành',
  cancelled: 'Đã hủy',
};

export const TASK_STATUS_BADGE_CLASSES = {
  todo: 'bg-gray-100 text-gray-700',
  in_progress: 'bg-orange-100 text-orange-800',
  in_review: 'bg-yellow-100 text-yellow-800',
  done: 'bg-green-100 text-green-800',
  cancelled: 'bg-gray-100 text-gray-500 line-through',
};

/**
 * Same status -> hue mapping as the badges, one shade lighter, used as the
 * background wash on Kanban cards so a card's status reads at a glance
 * without having to check its badge.
 */
export const TASK_STATUS_CARD_CLASSES = {
  todo: 'bg-white',
  in_progress: 'bg-orange-50',
  in_review: 'bg-yellow-50',
  done: 'bg-green-50',
  cancelled: 'bg-red-100/40',
};

/** Column order for the Kanban board. */
export const TASK_STATUS_COLUMNS = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
