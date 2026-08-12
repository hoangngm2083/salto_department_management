import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import DashboardWidgetCard from './DashboardWidgetCard';

const TOP_N = 5;

/**
 * "Dự án cần chú ý" (admin-only): active projects with the most overdue
 * tasks, top 5 - a daily action list instead of a static count (mục 7.4).
 * Filters/sorts/slices client-side from `projectsResource`, the "all
 * projects with `with_counts=1`" fetch shared with `DashboardSystemOverview`
 * (lifted to `DashboardPage` so both widgets don't each hit `GET /projects`).
 */
export default function DashboardAtRiskProjects({ projectsResource }) {
  const { data, loading, refreshing, error, refresh } = projectsResource;

  const atRisk = useMemo(
    () =>
      (data ?? [])
        .filter((project) => project.status === 'active' && (project.overdue_count ?? 0) > 0)
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
