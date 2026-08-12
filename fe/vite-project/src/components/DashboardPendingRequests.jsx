import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { listApprovals } from '../api/approvals';
import { listTaskDelayRequests } from '../api/taskDelayRequests';
import useAsyncResource from '../hooks/useAsyncResource';
import { WORKFLOW_TYPE_LABELS } from '../lib/workflow-type';
import DashboardWidgetCard from './DashboardWidgetCard';

const PREVIEW_SIZE = 5;
const EMPTY_PAGE = { data: [], meta: {} };

/**
 * Pending task delay requests and Approval Engine requests (role change and leave request
 * today, more workflow_type values in later phases) waiting on the viewer's decision -
 * previewed a few at a time with a link into the real list page to act on them, the dashboard
 * just needs to say "this is waiting on you" (mục 9.1), not replicate the full approve/reject
 * UI. Leave requests surface via the `pending_my_approval` approvals call below (they moved
 * onto the Approval Engine, Phase E.5) rather than a dedicated leave-requests call.
 *
 * Both self-scope by actor position on the backend (manager -> own managed projects, employee
 * -> own requests, admin unscoped), so this component doesn't need to know who's viewing it.
 * `GetTaskDelayRequestsRequest` scopes a manager to requests on projects they actively manage
 * plus their own submitted requests (see TaskDelayRequestService::getPaginated()) - no caller
 * flag needed.
 */
export default function DashboardPendingRequests({ title }) {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () =>
      Promise.all([
        listTaskDelayRequests({ status: 'pending', per_page: PREVIEW_SIZE }),
        listApprovals({ pending_my_approval: 1, per_page: PREVIEW_SIZE }),
      ]),
  });

  const [delayRequests, approvals] = data ?? [EMPTY_PAGE, EMPTY_PAGE];

  const items = useMemo(() => {
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

    return [...delays, ...approvalItems];
  }, [delayRequests, approvals]);

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
