import { useState } from 'react';
import { listApprovals } from '../api/approvals';
import ApprovalDetailModal from '../components/ApprovalDetailModal';
import CreateLeaveRequestModal from '../components/CreateLeaveRequestModal';
import Pager from '../components/Pager';
import useCursorList from '../hooks/useCursorList';
import { APPROVAL_STATUS_BADGE_CLASSES, APPROVAL_STATUS_LABELS } from '../lib/approval-status';
import { WORKFLOW_TYPE_LABELS } from '../lib/workflow-type';

const TABS = [
  { key: 'pending', label: 'Chờ tôi duyệt', params: { pending_my_approval: 1 } },
  { key: 'mine', label: 'Yêu cầu của tôi', params: { mine: 1 } },
];

/**
 * Generic across every Approval Engine workflow (project_role_change and leave_request so
 * far, Phase F/G add more workflow_type values later without needing a new list page) -
 * reuses the already-existing GET /api/approvals?mine=1|pending_my_approval=1 filters instead
 * of dedicated per-workflow index endpoints. Also the entry point for creating a leave
 * request - unlike role change (created contextually from a project's Members tab), leave
 * requests have no natural anchoring page, so creation lives here where the result shows up.
 */
export default function ApprovalsListPage() {
  const [tab, setTab] = useState('pending');
  const [selectedId, setSelectedId] = useState(null);
  const [showCreate, setShowCreate] = useState(false);

  const activeTab = TABS.find((t) => t.key === tab);

  const { items: approvals, meta, loading, refreshing, goToNext, goToPrev, refresh } = useCursorList({
    fetcher: listApprovals,
    params: activeTab.params,
  });

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Phê duyệt</h1>
        <button
          type="button"
          onClick={() => setShowCreate(true)}
          className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
        >
          Tạo yêu cầu nghỉ phép
        </button>
      </div>

      <div className="mb-4 flex gap-2 border-b border-gray-200">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={`border-b-2 px-3 py-2 text-sm font-medium ${
              tab === t.key ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Loại yêu cầu</th>
              <th className="px-4 py-2 font-medium">Người gửi</th>
              <th className="px-4 py-2 font-medium">Nhân viên liên quan</th>
              <th className="px-4 py-2 font-medium">Ngày gửi</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
            </tr>
          </thead>
          <tbody className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}>
            {loading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && approvals.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Không có yêu cầu nào.
                </td>
              </tr>
            )}

            {!loading &&
              approvals.map((approval) => (
                <tr
                  key={approval.id}
                  onClick={() => setSelectedId(approval.id)}
                  className="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50"
                >
                  <td className="px-4 py-2 font-medium text-gray-900">
                    {WORKFLOW_TYPE_LABELS[approval.workflow_type] ?? approval.workflow_type}
                  </td>
                  <td className="px-4 py-2 text-gray-500">{approval.requester_name}</td>
                  <td className="px-4 py-2 text-gray-500">{approval.subject_employee_name}</td>
                  <td className="px-4 py-2 text-gray-500">{approval.submitted_at?.slice(0, 10)}</td>
                  <td className="px-4 py-2">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                        APPROVAL_STATUS_BADGE_CLASSES[approval.status] ?? 'bg-gray-100 text-gray-700'
                      }`}
                    >
                      {APPROVAL_STATUS_LABELS[approval.status] ?? approval.status}
                    </span>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={goToPrev}
        onNext={goToNext}
      />

      {selectedId && (
        <ApprovalDetailModal approvalId={selectedId} onClose={() => setSelectedId(null)} onChanged={refresh} />
      )}

      {showCreate && <CreateLeaveRequestModal onClose={() => setShowCreate(false)} onCreated={refresh} />}
    </div>
  );
}
