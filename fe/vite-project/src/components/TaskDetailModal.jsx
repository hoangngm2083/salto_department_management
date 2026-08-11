import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { assignTask, getTask, updateTaskStatus } from '../api/tasks';
import { createTaskComment, listTaskComments } from '../api/taskComments';
import {
  createTaskDelayRequest,
  listTaskDelayRequests,
  updateTaskDelayRequestStatus,
} from '../api/taskDelayRequests';
import { useAuth } from '../context/useAuth';
import { DELAY_REQUEST_STATUS_BADGE_CLASSES, DELAY_REQUEST_STATUS_LABELS } from '../lib/task-delay-request-status';
import { CANCELLED_TITLE_CLASS, TASK_STATUS_BADGE_CLASSES, TASK_STATUS_LABELS } from '../lib/task-status';
import ProjectMemberSearchSelect from './ProjectMemberSearchSelect';

const TERMINAL_STATUSES = ['done', 'cancelled'];

/**
 * Shared task detail surface, opened from both `TasksPanel` (inside a
 * project - `canReview` reflects that project's own active PM/admin, mirrors
 * `TaskPolicy`'s `isProjectManager` check) and `EmployeeTasksPanel` (from an
 * employee profile, no project context so `canReview` stays false - only the
 * assignee's self-service transitions show there). The backend policy is the
 * real gate either way; this only decides which buttons are worth showing.
 *
 * `slug` is only available from the `TasksPanel` call site (it has the
 * project in scope) - that's also the only place `canReview` can be true, so
 * the assignee editor (project-manager only, per `TaskPolicy::assign`) never
 * needs to render without it.
 */
export default function TaskDetailModal({ task, canReview = false, slug, onClose, onUpdated }) {
  const { user } = useAuth();
  const [currentTask, setCurrentTask] = useState(task);
  const [comments, setComments] = useState([]);
  const [delayRequests, setDelayRequests] = useState([]);

  const [editingAssignee, setEditingAssignee] = useState(false);
  const [assigning, setAssigning] = useState(false);

  const [commentBody, setCommentBody] = useState('');
  const [postingComment, setPostingComment] = useState(false);

  const [transitioning, setTransitioning] = useState(false);
  const [transitionNote, setTransitionNote] = useState('');

  const [showDelayForm, setShowDelayForm] = useState(false);
  const [delayDate, setDelayDate] = useState('');
  const [delayReason, setDelayReason] = useState('');
  const [submittingDelay, setSubmittingDelay] = useState(false);
  const [reviewingDelayId, setReviewingDelayId] = useState(null);

  const isAssignee = currentTask.assigned_to === user.id;
  const isTerminal = TERMINAL_STATUSES.includes(currentTask.status);
  const isOverdue = currentTask.due_date && currentTask.due_date < new Date().toISOString().slice(0, 10) && !isTerminal;
  const pendingDelayRequest = delayRequests.find((r) => r.status === 'pending');

  const canSelfTransition = isAssignee && ['todo', 'in_progress'].includes(currentTask.status);
  const canReviewTransition = canReview && currentTask.status === 'in_review';
  const canCancel = canReview && !isTerminal;
  const showTransitionNote = canSelfTransition || canReviewTransition || canCancel;
  const canAssign = canReview && Boolean(slug);

  // Mirrors TaskPolicy::view()/comment(): a plain project member (or a
  // former, now-inactive PM) is not enough for either - only this project's
  // *active* PM (canReview), the assignee, or any system-role manager can
  // read the comment/delay-request thread; only the first two may write to
  // it. Skips the fetch entirely instead of calling an endpoint that would
  // just 403.
  const canViewThread = canReview || isAssignee || user.position === 'manager';
  const canPostComment = canReview || isAssignee;

  // Lazily seeded from canViewThread so a viewer without access never shows
  // a "loading" flash for a fetch that will never be made.
  const [loadingComments, setLoadingComments] = useState(canViewThread);
  const [loadingDelayRequests, setLoadingDelayRequests] = useState(canViewThread);

  function loadComments() {
    setLoadingComments(true);
    listTaskComments(currentTask.id, { per_page: 50 })
      .then((res) => setComments(res.data))
      .catch(() => {})
      .finally(() => setLoadingComments(false));
  }

  function loadDelayRequests() {
    setLoadingDelayRequests(true);
    listTaskDelayRequests({ task_id: currentTask.id, per_page: 50 })
      .then((res) => setDelayRequests(res.data))
      .catch(() => {})
      .finally(() => setLoadingDelayRequests(false));
  }

  useEffect(() => {
    if (!canViewThread) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    loadComments();
    loadDelayRequests();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentTask.id, canViewThread]);

  function applyUpdatedTask(updated) {
    setCurrentTask(updated);
    onUpdated?.(updated);
  }

  async function refreshTask() {
    try {
      applyUpdatedTask(await getTask(currentTask.id));
    } catch {
      // http.js interceptor already shows a toast for the error
    }
  }

  async function handleAssign(employeeId) {
    setAssigning(true);

    try {
      applyUpdatedTask(await assignTask(currentTask.id, { assigned_to: employeeId }));
      toast.success(employeeId ? 'Đã gán task.' : 'Đã bỏ gán task.');
      setEditingAssignee(false);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setAssigning(false);
    }
  }

  // The note field doubles as both `review_note` (only actually stored by
  // the backend for a PM's InReview -> Done/InProgress decision - see
  // TaskService::updateStatus) and, whenever non-empty, a trace comment
  // tagged with the target status - so any transition (not just a PM
  // reject) can carry a message, e.g. the assignee attaching a PR link when
  // submitting for review.
  async function handleTransition(status) {
    setTransitioning(true);
    const note = transitionNote.trim();

    try {
      const updated = await updateTaskStatus(currentTask.id, { status, review_note: note || undefined });
      applyUpdatedTask(updated);

      if (note) {
        await createTaskComment(currentTask.id, { body: note, task_status: status }).catch(() => {});
        loadComments();
      }

      toast.success('Đã cập nhật trạng thái task.');
      setTransitionNote('');
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setTransitioning(false);
    }
  }

  function handleCancel() {
    if (window.confirm('Hủy task này?')) {
      handleTransition('cancelled');
    }
  }

  async function handlePostComment(e) {
    e.preventDefault();

    if (!commentBody.trim()) {
      return;
    }

    setPostingComment(true);

    try {
      await createTaskComment(currentTask.id, { body: commentBody });
      setCommentBody('');
      loadComments();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setPostingComment(false);
    }
  }

  async function handleSubmitDelayRequest(e) {
    e.preventDefault();
    setSubmittingDelay(true);

    try {
      await createTaskDelayRequest({
        task_id: currentTask.id,
        requested_due_date: delayDate,
        reason: delayReason,
      });
      toast.success('Đã gửi yêu cầu gia hạn.');
      setShowDelayForm(false);
      setDelayDate('');
      setDelayReason('');
      loadDelayRequests();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmittingDelay(false);
    }
  }

  async function handleDelayReview(delayRequest, status) {
    if (status === 'rejected' && !window.confirm('Từ chối yêu cầu gia hạn này?')) {
      return;
    }

    if (status === 'cancelled' && !window.confirm('Hủy yêu cầu gia hạn này?')) {
      return;
    }

    setReviewingDelayId(delayRequest.id);

    try {
      await updateTaskDelayRequestStatus(delayRequest.id, { status });
      toast.success(
        status === 'approved' ? 'Đã duyệt gia hạn.' : status === 'rejected' ? 'Đã từ chối gia hạn.' : 'Đã hủy yêu cầu.'
      );
      loadDelayRequests();

      if (status === 'approved') {
        await refreshTask();
      }
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setReviewingDelayId(null);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" onClick={onClose}>
      <div
        className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-start justify-between">
          <div>
            <h2
              className={`text-lg font-semibold text-gray-900 ${currentTask.status === 'cancelled' ? CANCELLED_TITLE_CLASS : ''}`}
            >
              {currentTask.title}
            </h2>
            {currentTask.project_name && <p className="text-xs text-gray-500">{currentTask.project_name}</p>}
          </div>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Đóng">
            &times;
          </button>
        </div>

        <div className="mb-4 flex flex-wrap items-center gap-2">
          <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${TASK_STATUS_BADGE_CLASSES[currentTask.status] ?? 'bg-gray-100 text-gray-700'}`}
          >
            {TASK_STATUS_LABELS[currentTask.status] ?? currentTask.status}
          </span>
          {(currentTask.assignee_name || canAssign) && !editingAssignee && (
            <span className="flex items-center gap-1 text-xs">
              <span className="text-gray-400">Giao cho: </span>
              <span className="font-medium text-gray-700">{currentTask.assignee_name ?? 'Chưa giao'}</span>
              {canAssign && (
                <button
                  type="button"
                  onClick={() => setEditingAssignee(true)}
                  disabled={assigning}
                  className="text-gray-400 underline decoration-dotted hover:text-gray-600"
                >
                  Đổi
                </button>
              )}
            </span>
          )}
          {canAssign && editingAssignee && (
            <span className="flex items-center gap-1 text-xs">
              <span className="text-gray-400">Giao cho: </span>
              <span className="w-52">
                <ProjectMemberSearchSelect
                  slug={slug}
                  value={currentTask.assigned_to}
                  valueLabel={currentTask.assignee_name}
                  onChange={(id) => handleAssign(id)}
                />
              </span>
              <button
                type="button"
                onClick={() => setEditingAssignee(false)}
                disabled={assigning}
                className="text-gray-400 hover:text-gray-600"
                aria-label="Hủy"
              >
                &times;
              </button>
            </span>
          )}
          {currentTask.due_date && (
            <span className="text-xs">
              <span className={isOverdue ? 'text-red-400' : 'text-gray-400'}>Hạn: </span>
              <span className={`font-medium ${isOverdue ? 'text-red-600' : 'text-gray-700'}`}>
                {currentTask.due_date}
                {isOverdue && ' (quá hạn)'}
              </span>
            </span>
          )}
        </div>

        {currentTask.description && (
          <p className="mb-4 whitespace-pre-wrap text-sm text-gray-700">{currentTask.description}</p>
        )}

        {currentTask.review_note && (
          <p className="mb-4 rounded-md bg-yellow-50 p-2 text-xs text-yellow-800">
            Ghi chú của người duyệt: {currentTask.review_note}
          </p>
        )}

        {showTransitionNote && (
          <textarea
            value={transitionNote}
            onChange={(e) => setTransitionNote(e.target.value)}
            placeholder="Ghi chú kèm theo khi đổi trạng thái (không bắt buộc, vd: link PR, lý do từ chối)..."
            rows={2}
            maxLength={2000}
            className="mb-2 w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs"
          />
        )}

        <div className="mb-6 flex flex-wrap items-center gap-2">
          {canSelfTransition && currentTask.status === 'todo' && (
            <button
              type="button"
              disabled={transitioning}
              onClick={() => handleTransition('in_progress')}
              className="rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-700 disabled:opacity-50 hover:bg-blue-50"
            >
              Bắt đầu làm
            </button>
          )}

          {canSelfTransition && currentTask.status === 'in_progress' && (
            <button
              type="button"
              disabled={transitioning}
              onClick={() => handleTransition('in_review')}
              className="rounded-md border border-yellow-200 px-3 py-1.5 text-xs font-medium text-yellow-700 disabled:opacity-50 hover:bg-yellow-50"
            >
              Nộp duyệt
            </button>
          )}

          {canReviewTransition && (
            <>
              <button
                type="button"
                disabled={transitioning}
                onClick={() => handleTransition('done')}
                className="rounded-md border border-green-200 px-3 py-1.5 text-xs font-medium text-green-700 disabled:opacity-50 hover:bg-green-50"
              >
                Duyệt hoàn thành
              </button>
              <button
                type="button"
                disabled={transitioning}
                onClick={() => handleTransition('in_progress')}
                className="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
              >
                Từ chối, yêu cầu làm lại
              </button>
            </>
          )}

          {canCancel && (
            <button
              type="button"
              disabled={transitioning}
              onClick={handleCancel}
              className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
            >
              Hủy task
            </button>
          )}
        </div>

        <div className="mb-6 rounded-md border border-gray-200 p-3">
          <h3 className="mb-2 text-sm font-medium text-gray-900">Yêu cầu gia hạn</h3>

          {!canViewThread && (
            <p className="text-xs text-gray-400">Bạn không có quyền xem yêu cầu gia hạn của task này.</p>
          )}

          {canViewThread && loadingDelayRequests && <p className="text-xs text-gray-500">Đang tải...</p>}

          {canViewThread && !loadingDelayRequests && delayRequests.length === 0 && (
            <p className="text-xs text-gray-500">Chưa có yêu cầu gia hạn nào.</p>
          )}

          {canViewThread && !loadingDelayRequests && delayRequests.length > 0 && (
            <ul className="space-y-2">
              {delayRequests.map((delayRequest) => (
                <li key={delayRequest.id} className="flex flex-wrap items-center gap-2 text-xs">
                  <span
                    className={`rounded-full px-2 py-0.5 font-medium ${DELAY_REQUEST_STATUS_BADGE_CLASSES[delayRequest.status] ?? 'bg-gray-100 text-gray-700'}`}
                  >
                    {DELAY_REQUEST_STATUS_LABELS[delayRequest.status] ?? delayRequest.status}
                  </span>
                  <span className="text-gray-600">
                    {delayRequest.current_due_date} &rarr; {delayRequest.requested_due_date}
                  </span>
                  <span className="text-gray-400">({delayRequest.reason})</span>

                  {delayRequest.status === 'pending' && canReview && (
                    <span className="flex gap-1">
                      <button
                        type="button"
                        disabled={reviewingDelayId === delayRequest.id}
                        onClick={() => handleDelayReview(delayRequest, 'approved')}
                        className="rounded-md border border-green-200 px-2 py-0.5 font-medium text-green-700 disabled:opacity-50 hover:bg-green-50"
                      >
                        Duyệt
                      </button>
                      <button
                        type="button"
                        disabled={reviewingDelayId === delayRequest.id}
                        onClick={() => handleDelayReview(delayRequest, 'rejected')}
                        className="rounded-md border border-red-200 px-2 py-0.5 font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                      >
                        Từ chối
                      </button>
                    </span>
                  )}

                  {delayRequest.status === 'pending' && isAssignee && (
                    <button
                      type="button"
                      disabled={reviewingDelayId === delayRequest.id}
                      onClick={() => handleDelayReview(delayRequest, 'cancelled')}
                      className="rounded-md border border-gray-300 px-2 py-0.5 font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                    >
                      Hủy yêu cầu
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}

          {isAssignee && !pendingDelayRequest && !isTerminal && currentTask.due_date && (
            <div className="mt-3">
              {!showDelayForm ? (
                <button
                  type="button"
                  onClick={() => setShowDelayForm(true)}
                  className="rounded-md border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100"
                >
                  + Yêu cầu gia hạn
                </button>
              ) : (
                <form onSubmit={handleSubmitDelayRequest} className="space-y-2">
                  <input
                    type="date"
                    required
                    value={delayDate}
                    onChange={(e) => setDelayDate(e.target.value)}
                    min={currentTask.due_date}
                    className="w-full rounded-md border border-gray-300 px-2 py-1 text-xs"
                  />
                  <textarea
                    required
                    rows={2}
                    value={delayReason}
                    onChange={(e) => setDelayReason(e.target.value)}
                    placeholder="Lý do xin gia hạn"
                    className="w-full rounded-md border border-gray-300 px-2 py-1 text-xs"
                  />
                  <div className="flex justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => setShowDelayForm(false)}
                      className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                    >
                      Hủy
                    </button>
                    <button
                      type="submit"
                      disabled={submittingDelay}
                      className="rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white disabled:opacity-50 hover:bg-gray-700"
                    >
                      {submittingDelay ? 'Đang gửi...' : 'Gửi yêu cầu'}
                    </button>
                  </div>
                </form>
              )}
            </div>
          )}
        </div>

        <div>
          <h3 className="mb-2 text-sm font-medium text-gray-900">Bình luận</h3>

          {!canViewThread && (
            <p className="text-xs text-gray-400">Bạn không có quyền xem bình luận của task này.</p>
          )}

          {canViewThread && loadingComments && <p className="text-xs text-gray-500">Đang tải...</p>}

          {canViewThread && !loadingComments && comments.length === 0 && (
            <p className="mb-3 text-xs text-gray-500">Chưa có bình luận nào.</p>
          )}

          {canViewThread && !loadingComments && comments.length > 0 && (
            <ul className="mb-3 space-y-2">
              {comments.map((comment) => (
                <li key={comment.id} className="rounded-md bg-gray-50 p-2 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-gray-900">{comment.employee_name}</span>
                    <span className="text-gray-400">{comment.created_at?.slice(0, 16).replace('T', ' ')}</span>
                  </div>
                  <p className="mt-1 text-gray-700">{comment.body}</p>
                </li>
              ))}
            </ul>
          )}

          {canPostComment && (
            <form onSubmit={handlePostComment} className="flex gap-2">
              <input
                type="text"
                value={commentBody}
                onChange={(e) => setCommentBody(e.target.value)}
                placeholder="Viết bình luận..."
                maxLength={2000}
                className="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
              />
              <button
                type="submit"
                disabled={postingComment || !commentBody.trim()}
                className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
              >
                Gửi
              </button>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
