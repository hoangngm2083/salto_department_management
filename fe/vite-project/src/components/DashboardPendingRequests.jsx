import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { listApprovals } from '../api/approvals';
import { listLeaveRequests } from '../api/leaveRequests';
import { listTaskDelayRequests } from '../api/taskDelayRequests';
import useAsyncResource from '../hooks/useAsyncResource';
import { WORKFLOW_TYPE_LABELS } from '../lib/workflow-type';
import DashboardWidgetCard from './DashboardWidgetCard';

const PREVIEW_SIZE = 5;
const EMPTY_PAGE = { data: [], meta: {} };

/**
 * Pending leave requests, optionally task delay requests, and Approval Engine requests
 * (role change today, more workflow_type values in later phases) waiting on the viewer's
 * decision - previewed a few at a time with a link into the real list page to act on them,
 * the dashboard just needs to say "this is waiting on you" (mục 9.1), not replicate the full
 * approve/reject UI.
 *
 * Leave/task-delay requests self-scope by actor position on the backend (manager -> own
 * department / employee -> own requests; admin unscoped), so this component doesn't need to
 * know who's viewing it - except for `includeTaskDelayRequests`: unlike leave requests,
 * GetTaskDelayRequestsRequest does NOT scope a manager to the projects they actually manage
 * (mục 7.9/7.10), so a manager here would see requests they have no authority over. Callers
 * pass `includeTaskDelayRequests={false}` for the manager dashboard for that reason; employee
 * (self-scoped) and admin (sees everything, acts on everything) both pass `true`. Approvals
 * don't have this problem - `pending_my_approval=1` is precise per-project (the resolved PM
 * step), so it's always included regardless of `includeTaskDelayRequests`.
 */
export default function DashboardPendingRequests({ title, includeTaskDelayRequests }) {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () =>
      Promise.all([
        listLeaveRequests({ status: 'pending', per_page: PREVIEW_SIZE }),
        includeTaskDelayRequests
          ? listTaskDelayRequests({ status: 'pending', per_page: PREVIEW_SIZE })
          : Promise.resolve(EMPTY_PAGE),
        listApprovals({ pending_my_approval: 1, per_page: PREVIEW_SIZE }),
      ]),
    deps: [includeTaskDelayRequests],
  });

  const [leaveRequests, delayRequests, approvals] = data ?? [EMPTY_PAGE, EMPTY_PAGE, EMPTY_PAGE];

  const items = useMemo(() => {
    const leaves = (leaveRequests?.data ?? []).map((request) => ({
      key: `leave-${request.id}`,
      to: '/leave-requests',
      label: request.employee_name ? `${request.employee_name} · nghỉ phép` : 'Yêu cầu nghỉ phép',
      detail: `${request.start_date} → ${request.end_date}`,
    }));

    const delays = (delayRequests?.data ?? []).map((request) => ({
      key: `delay-${request.id}`,
      to: '/task-delay-requests',
      label: request.task_title,
      detail: request.requester_name
        ? `${request.requester_name} · dời hạn sang ${request.requested_due_date}`
        : `Dời hạn sang ${request.requested_due_date}`,
    }));

    const approvalItems = (approvals?.data ?? []).map((approval) => ({
      key: `approval-${approval.id}`,
      to: '/approvals',
      label: WORKFLOW_TYPE_LABELS[approval.workflow_type] ?? approval.workflow_type,
      detail: approval.subject_employee_name
        ? `${approval.subject_employee_name} · gửi bởi ${approval.requester_name}`
        : `Gửi bởi ${approval.requester_name}`,
    }));

    return [...leaves, ...delays, ...approvalItems];
  }, [leaveRequests, delayRequests, approvals]);

  return (
    <DashboardWidgetCard
      title={title}
      loading={loading}
      refreshing={refreshing}
      error={error}
      onRefresh={refresh}
      empty={!loading && items.length === 0}
      emptyText="Không có yêu cầu nào đang chờ."
    >
      <ul className="divide-y divide-gray-100">
        {items.map((item) => (
          <li key={item.key}>
            <Link to={item.to} className="block py-2 text-sm hover:bg-gray-50">
              <p className="font-medium text-gray-900">{item.label}</p>
              <p className="text-xs text-gray-500">{item.detail}</p>
            </Link>
          </li>
        ))}
      </ul>
    </DashboardWidgetCard>
  );
}
