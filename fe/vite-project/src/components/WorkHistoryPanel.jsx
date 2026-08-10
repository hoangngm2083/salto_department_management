import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getEmployeeWorkHistory } from '../api/employees';
import { getTimelineRange } from '../lib/timeline';
import { TimelineRow, TimelineTable, TimelineTableHeader } from './Timeline';

/**
 * Gantt view of an employee's project history - one row per project
 * (current and past), each with the role period(s) held within it. Reuses
 * the same `Timeline` building blocks as `AssignmentsPanel`'s project
 * members view, with the range spanning the employee's whole history
 * instead of a single project's start/end date.
 */
export default function WorkHistoryPanel({ employeeId }) {
  const [history, setHistory] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setLoading(true);
    setError(false);

    getEmployeeWorkHistory(employeeId)
      .then(setHistory)
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [employeeId]);

  if (loading) {
    return (
      <div className="mx-auto mt-6 max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <p className="text-sm text-gray-500">Đang tải quá trình làm việc...</p>
      </div>
    );
  }

  if (error || !history) {
    return null;
  }

  const { start: rangeStart, end: rangeEnd } = getTimelineRange({ periods: history.projects });

  return (
    <div className="mx-auto mt-6 max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-lg font-medium text-gray-900">Quá trình làm việc</h2>

      {history.projects.length === 0 && (
        <p className="text-sm text-gray-500">Chưa tham gia dự án nào.</p>
      )}

      {history.projects.length > 0 && (
        <TimelineTable>
          <TimelineTableHeader label="Dự án" rangeStart={rangeStart} rangeEnd={rangeEnd} />

          <ul className="divide-y divide-gray-100">
            {history.projects.map((project, index) => (
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

                <ul className="mt-2 space-y-1">
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
