import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { listProjects } from '../api/projects';
import useAsyncResource from '../hooks/useAsyncResource';
import DashboardWidgetCard from './DashboardWidgetCard';

const PAGE_SIZE = 100;
const TOP_N = 5;

/**
 * "Dự án cần chú ý" (admin-only): active projects with the most overdue
 * tasks, top 5 - a daily action list instead of a static count (mục 7.4).
 * Fetches independently with `with_counts=1` opted in (`GET /projects` keeps
 * counts off by default to avoid N+1 - mục 7.9/7.2), then sorts/slices
 * client-side; no dedicated endpoint needed.
 */
export default function DashboardAtRiskProjects() {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () => listProjects({ status: 'active', per_page: PAGE_SIZE, with_counts: 1 }).then((res) => res.data),
  });

  const atRisk = useMemo(
    () =>
      (data ?? [])
        .filter((project) => (project.overdue_count ?? 0) > 0)
        .sort((a, b) => b.overdue_count - a.overdue_count)
        .slice(0, TOP_N),
    [data]
  );

  return (
    <DashboardWidgetCard
      title="Dự án cần chú ý"
      loading={loading}
      refreshing={refreshing}
      error={error}
      onRefresh={refresh}
      empty={!loading && atRisk.length === 0}
      emptyText="Không có dự án nào đang quá hạn."
    >
      <ul className="divide-y divide-gray-100">
        {atRisk.map((project) => (
          <li key={project.id} className="flex items-center justify-between gap-2 py-2 text-sm">
            <Link to={`/projects/${project.slug}`} className="font-medium text-gray-900 hover:underline">
              {project.name}
            </Link>
            <span className="shrink-0 text-xs font-medium text-red-600">{project.overdue_count} task quá hạn</span>
          </li>
        ))}
      </ul>
    </DashboardWidgetCard>
  );
}
