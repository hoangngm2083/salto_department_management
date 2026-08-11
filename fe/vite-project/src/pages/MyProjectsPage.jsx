import { useState } from 'react';
import { Link } from 'react-router-dom';
import { getEmployeeManagedProjects, getEmployeeWorkHistory } from '../api/employees';
import { useAuth } from '../context/useAuth';
import useAsyncResource from '../hooks/useAsyncResource';
import { mergeMyProjects } from '../lib/my-projects';
import { PROJECT_STATUS_OPTIONS } from '../lib/project-status';

const PROJECT_STATUS_LABELS = Object.fromEntries(PROJECT_STATUS_OPTIONS.map((option) => [option.value, option.label]));

/**
 * "Dự án của tôi" - flat list of every project the current employee is
 * currently active on, either as an assignee or as PM, linked from the
 * sidebar (mục 9's "Dự án đang tham gia" note). An employee has no `/projects`
 * list (admin/manager only), so this is otherwise their only way to browse
 * their projects - it must therefore cover both relationships, unlike the
 * dashboard's two separate widgets (`DashboardMyProjects`/`DashboardManagedProjects`),
 * which deliberately stay split for their own UX reasons.
 */
export default function MyProjectsPage() {
  const { user } = useAuth();
  const [search, setSearch] = useState('');

  const { data, loading, refreshing, error } = useAsyncResource({
    fetcher: () =>
      Promise.all([getEmployeeWorkHistory(user.id, { active: 1 }), getEmployeeManagedProjects(user.id)]).then(
        ([history, managed]) => mergeMyProjects(history.projects, managed, user.id)
      ),
    deps: [user.id],
  });

  const projects = data ?? [];
  const filtered = projects.filter((p) => p.project.toLowerCase().includes(search.trim().toLowerCase()));

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Dự án của tôi</h1>
      </div>

      <div className="mb-4 flex gap-3">
        <input
          type="text"
          placeholder="Tìm kiếm theo tên..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        />
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Tên dự án</th>
              <th className="px-4 py-2 font-medium">Vai trò</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
              <th className="px-4 py-2 font-medium">Từ ngày</th>
            </tr>
          </thead>
          <tbody className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}>
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && error && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-red-600">
                  Không thể tải danh sách dự án.
                </td>
              </tr>
            )}

            {!loading && !error && filtered.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                  Chưa tham gia dự án nào.
                </td>
              </tr>
            )}

            {!loading &&
              !error &&
              filtered.map((project, index) => {
                const activeRoles = project.roles.filter((role) => !role.end_date).map((role) => role.role);

                return (
                  <tr key={index} className="border-b border-gray-100 last:border-0">
                    <td className="px-4 py-2">
                      <Link to={`/projects/${project.project_slug}`} className="font-medium text-gray-900 hover:underline">
                        {project.project}
                      </Link>
                    </td>
                    <td className="px-4 py-2 text-gray-500">{activeRoles.join(', ') || '—'}</td>
                    <td className="px-4 py-2">
                      <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                        {PROJECT_STATUS_LABELS[project.project_status] ?? project.project_status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-gray-500">{project.start_date}</td>
                  </tr>
                );
              })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
