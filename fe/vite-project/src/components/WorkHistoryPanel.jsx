import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getEmployeeManagedProjects, getEmployeeWorkHistory } from '../api/employees';
import { getTimelineRange, getZoomedRange } from '../lib/timeline';
import { mergeMyProjects } from '../lib/my-projects';
import { TimelineRow, TimelineTable, TimelineTableHeader, TimelineZoomControls } from './Timeline';

/**
 * Gantt view of an employee's project history - one row per project
 * (current and past) they were assigned to or managed, each with the role
 * period(s) held within it (a currently-active PM-ship renders as its own
 * "Project Manager" role period, via `mergeMyProjects`). Reuses the same
 * `Timeline` building blocks as `AssignmentsPanel`'s project members view,
 * with the range spanning the employee's whole history instead of a single
 * project's start/end date.
 */
export default function WorkHistoryPanel({ employeeId }) {
  const [projects, setProjects] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [zoomMonths, setZoomMonths] = useState(null);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setLoading(true);
    setError(false);

    Promise.all([getEmployeeWorkHistory(employeeId), getEmployeeManagedProjects(employeeId)])
      .then(([history, managed]) => setProjects(mergeMyProjects(history.projects, managed, employeeId)))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [employeeId]);

  if (loading) {
    return (
      <div className="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <p className="text-sm text-gray-500">Đang tải quá trình làm việc...</p>
      </div>
    );
  }

  if (error || !projects) {
    return null;
  }

  const { start: overallStart, end: overallEnd } = getTimelineRange({ periods: projects });
  const { start: rangeStart, end: rangeEnd } = zoomMonths
    ? getZoomedRange(overallStart, overallEnd, zoomMonths)
    : { start: overallStart, end: overallEnd };

  return (
    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-medium text-gray-900">Quá trình làm việc</h2>
        {projects.length > 0 && <TimelineZoomControls value={zoomMonths} onChange={setZoomMonths} />}
      </div>

      {projects.length === 0 && (
        <p className="text-sm text-gray-500">Chưa tham gia dự án nào.</p>
      )}

      {projects.length > 0 && (
        <TimelineTable>
          <TimelineTableHeader label="Dự án" rangeStart={rangeStart} rangeEnd={rangeEnd} />

          <ul className="divide-y divide-gray-100">
            {projects.map((project, index) => (
              <TimelineRow
                key={index}
                rangeStart={rangeStart}
                rangeEnd={rangeEnd}
                startDate={project.start_date}
                endDate={project.end_date}
              >
                <div className="flex items-center gap-2">
                  <Link
                    to={`/projects/${project.project_slug}`}
                    className="text-sm font-medium text-gray-900 hover:underline"
                  >
                    {project.project}
                  </Link>
                  <StatusBadge active={!project.end_date} />
                </div>

                <ul className="mt-1 space-y-0.5">
                  {project.roles.map((role, roleIndex) => (
                    <li key={roleIndex} className="flex items-center gap-2 text-xs text-gray-600">
                      <span>{role.role}</span>
                      <span className="text-gray-400">
                        ({role.start_date} &rarr; {role.end_date ?? 'hiện tại'})
                      </span>
                      <StatusBadge active={!role.end_date} small />
                    </li>
                  ))}
                </ul>
              </TimelineRow>
            ))}
          </ul>
        </TimelineTable>
      )}
    </div>
  );
}

function StatusBadge({ active, small }) {
  return (
    <span
      className={`rounded-full px-2 py-0.5 font-medium ${small ? 'text-[10px]' : 'text-xs'} ${
        active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'
      }`}
    >
      {active ? 'Đang hoạt động' : 'Đã kết thúc'}
    </span>
  );
}
