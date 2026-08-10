import { useState } from 'react';
import { toast } from 'sonner';
import {
  addAssignmentRole,
  createProjectAssignment,
  endAssignmentRole,
  endProjectAssignment,
  listProjectAssignments,
} from '../api/projectAssignments';
import { listProjectRoles } from '../api/projectRoles';
import useCursorList from '../hooks/useCursorList';
import { getTimelineRange } from '../lib/timeline';
import EmployeeMultiSelect from './EmployeeMultiSelect';
import Pager from './Pager';
import ProjectRoleMultiSelect from './ProjectRoleMultiSelect';
import { TimelineRow, TimelineTable, TimelineTableHeader } from './Timeline';

/**
 * "Members" tab of a project's detail page: every assignment (active and
 * past) rendered as a timeline row, and within each, the role period(s)
 * currently held. Ending an assignment or a role period is immediate
 * (today) - unlike project managers there's no "must always have >= 1
 * active role" invariant, so ending the last role is allowed.
 */
export default function AssignmentsPanel({ project, canManage }) {
  const { items: assignments, meta, loading, refreshing, goToNext, goToPrev, refresh } =
    useCursorList({
      fetcher: (params) => listProjectAssignments(project.slug, params),
      params: {},
    });

  const { start: rangeStart, end: rangeEnd } = getTimelineRange({
    boundStart: project.start_date,
    boundEnd: project.end_date,
    periods: assignments,
  });

  const [showAdd, setShowAdd] = useState(false);
  const [newEmployees, setNewEmployees] = useState([]);
  const [newRoleIds, setNewRoleIds] = useState([]);
  const [newStartDate, setNewStartDate] = useState('');
  const [adding, setAdding] = useState(false);

  const [endingId, setEndingId] = useState(null);

  function cancelAdd() {
    setShowAdd(false);
    setNewEmployees([]);
    setNewRoleIds([]);
    setNewStartDate('');
  }

  async function handleAdd() {
    if (newEmployees.length === 0 || newRoleIds.length === 0) {
      return;
    }

    setAdding(true);

    try {
      await createProjectAssignment(project.slug, {
        employee_ids: newEmployees.map((employee) => employee.id),
        role_ids: newRoleIds,
        start_date: newStartDate || undefined,
      });
      toast.success(
        newEmployees.length > 1
          ? 'Thêm các thành viên vào dự án thành công.'
          : 'Thêm thành viên vào dự án thành công.'
      );
      cancelAdd();
      refresh();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setAdding(false);
    }
  }

  async function handleEnd(assignment) {
    if (
      !window.confirm(`Kết thúc sự tham gia của "${assignment.employee_name}" trong dự án này?`)
    ) {
      return;
    }

    setEndingId(assignment.id);

    try {
      await endProjectAssignment(project.slug, assignment.id);
      toast.success('Đã kết thúc sự tham gia của thành viên.');
      refresh();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setEndingId(null);
    }
  }

  async function handleEndRole(assignment, rolePeriod) {
    if (!window.confirm(`Kết thúc vai trò "${rolePeriod.project_role_name}"?`)) {
      return;
    }

    try {
      await endAssignmentRole(project.slug, assignment.id, rolePeriod.id);
      toast.success('Đã kết thúc vai trò.');
      refresh();
    } catch {
      // http.js interceptor already shows a toast for the error
    }
  }

  async function handleAddRole(assignment, roleId) {
    await addAssignmentRole(project.slug, assignment.id, roleId);
    toast.success('Đã thêm vai trò.');
    refresh();
  }

  return (
    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-medium text-gray-900">Thành viên dự án</h2>
        {canManage && !showAdd && (
          <button
            type="button"
            onClick={() => setShowAdd(true)}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Thêm thành viên
          </button>
        )}
      </div>

      {canManage && showAdd && (
        <div className="mb-4 space-y-3 rounded-md border border-gray-200 p-3">
          <div>
            <span className="mb-1 block text-xs font-medium text-gray-700">Nhân viên</span>
            <EmployeeMultiSelect selected={newEmployees} onChange={setNewEmployees} />
          </div>

          <div>
            <span className="mb-1 block text-xs font-medium text-gray-700">Vai trò</span>
            <ProjectRoleMultiSelect selected={newRoleIds} onChange={setNewRoleIds} />
          </div>

          <label className="block">
            <span className="mb-1 block text-xs font-medium text-gray-700">
              Ngày bắt đầu (để trống = hôm nay)
            </span>
            <input
              type="date"
              value={newStartDate}
              onChange={(e) => setNewStartDate(e.target.value)}
              className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
          </label>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={cancelAdd}
              disabled={adding}
              className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
            >
              Hủy
            </button>
            <button
              type="button"
              onClick={handleAdd}
              disabled={adding || newEmployees.length === 0 || newRoleIds.length === 0}
              className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
            >
              {adding ? 'Đang thêm...' : 'Thêm'}
            </button>
          </div>
        </div>
      )}

      {loading && <p className="py-2 text-sm text-gray-500">Đang tải...</p>}

      {!loading && assignments.length === 0 && (
        <p className="py-2 text-sm text-gray-500">Chưa có thành viên nào.</p>
      )}

      {!loading && assignments.length > 0 && (
        <TimelineTable dimmed={refreshing}>
          <TimelineTableHeader label="Thành viên" rangeStart={rangeStart} rangeEnd={rangeEnd} />

          <ul className="divide-y divide-gray-100">
            {assignments.map((assignment) => (
              <AssignmentRow
                key={assignment.id}
                assignment={assignment}
                canManage={canManage}
                ending={endingId === assignment.id}
                rangeStart={rangeStart}
                rangeEnd={rangeEnd}
                onEnd={() => handleEnd(assignment)}
                onEndRole={(rolePeriod) => handleEndRole(assignment, rolePeriod)}
                onAddRole={(roleId) => handleAddRole(assignment, roleId)}
              />
            ))}
          </ul>
        </TimelineTable>
      )}

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={goToPrev}
        onNext={goToNext}
      />
    </div>
  );
}

function AssignmentRow({ assignment, canManage, ending, rangeStart, rangeEnd, onEnd, onEndRole, onAddRole }) {
  const [showAddRole, setShowAddRole] = useState(false);
  const [roleOptions, setRoleOptions] = useState([]);
  const [newRoleId, setNewRoleId] = useState('');
  const [addingRole, setAddingRole] = useState(false);

  const isActive = !assignment.end_date;
  const activeRolePeriods = assignment.role_periods.filter((period) => !period.end_date);
  const endedRolePeriods = assignment.role_periods.filter((period) => period.end_date);
  const activeRoleIds = activeRolePeriods.map((period) => period.project_role_id);

  function openAddRole() {
    setShowAddRole(true);
    listProjectRoles({ status: 'active', per_page: 100 })
      .then((res) => setRoleOptions(res.data.filter((role) => !activeRoleIds.includes(role.id))))
      .catch(() => {});
  }

  async function submitAddRole() {
    if (!newRoleId) {
      return;
    }

    setAddingRole(true);

    try {
      await onAddRole(Number(newRoleId));
      setShowAddRole(false);
      setNewRoleId('');
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setAddingRole(false);
    }
  }

  return (
    <TimelineRow
      rangeStart={rangeStart}
      rangeEnd={rangeEnd}
      startDate={assignment.start_date}
      endDate={assignment.end_date}
    >
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm font-medium text-gray-900">{assignment.employee_name}</p>

        {canManage && isActive && (
          <button
            type="button"
            onClick={onEnd}
            disabled={ending}
            className="shrink-0 rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
          >
            {ending ? 'Đang kết thúc...' : 'Kết thúc'}
          </button>
        )}
      </div>

      <div className="mt-2 flex flex-wrap items-center gap-1.5">
        {activeRolePeriods.map((period) => (
          <span
            key={period.id}
            className="flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700"
          >
            {period.project_role_name}
            {canManage && (
              <button
                type="button"
                onClick={() => onEndRole(period)}
                className="text-blue-400 hover:text-blue-600"
                aria-label={`Kết thúc vai trò ${period.project_role_name}`}
              >
                &times;
              </button>
            )}
          </span>
        ))}

        {endedRolePeriods.map((period) => (
          <span
            key={period.id}
            className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-400 line-through"
            title={`${period.start_date} → ${period.end_date}`}
          >
            {period.project_role_name}
          </span>
        ))}

        {canManage && isActive && !showAddRole && (
          <button
            type="button"
            onClick={openAddRole}
            className="rounded-full border border-dashed border-gray-300 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-100"
          >
            + Thêm vai trò
          </button>
        )}
      </div>

      {canManage && isActive && showAddRole && (
        <div className="mt-2 flex items-center gap-2">
          <select
            value={newRoleId}
            onChange={(e) => setNewRoleId(e.target.value)}
            className="rounded-md border border-gray-300 px-2 py-1 text-xs"
          >
            <option value="">Chọn vai trò...</option>
            {roleOptions.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            onClick={submitAddRole}
            disabled={!newRoleId || addingRole}
            className="rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white disabled:opacity-50 hover:bg-gray-700"
          >
            {addingRole ? 'Đang thêm...' : 'Thêm'}
          </button>
          <button
            type="button"
            onClick={() => setShowAddRole(false)}
            className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
          >
            Hủy
          </button>
        </div>
      )}
    </TimelineRow>
  );
}
