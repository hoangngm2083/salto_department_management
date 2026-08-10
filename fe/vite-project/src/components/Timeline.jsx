import { clampIso, diffDays, getTimelineTicks, MIN_BAR_WIDTH_PERCENT } from '../lib/timeline';

/**
 * Gantt-style timeline building blocks, generic over any "row has a
 * start/end date" data - project assignments, role periods, an employee's
 * project history, etc. Each row's own start/end is clamped into the
 * timeline's resolved `[rangeStart, rangeEnd]` window (see
 * `getTimelineRange` in `lib/timeline`), so a date outside that window never
 * breaks the layout - it just touches the corresponding edge.
 */

/** Axis row of evenly spaced date ticks spanning the resolved range. */
export function TimelineHeader({ rangeStart, rangeEnd }) {
  const ticks = getTimelineTicks(rangeStart, rangeEnd);
  const lastIndex = ticks.length - 1;

  return (
    <div className="relative h-5 text-[10px] text-gray-400">
      {ticks.map((tick, i) => {
        const alignClass = i === 0 ? '' : i === lastIndex ? '-translate-x-full' : '-translate-x-1/2';

        return (
          <span
            key={tick.date}
            className={`absolute top-0 whitespace-nowrap ${alignClass}`}
            style={{ left: `${tick.percent}%` }}
          >
            {tick.date}
          </span>
        );
      })}
    </div>
  );
}

/**
 * Vertical gridlines at the same offsets as the header ticks, sitting behind
 * a row's bar. Placed inside a `relative` cell that flexbox stretches to the
 * row's full height, so the lines always span exactly that row - no manual
 * height bookkeeping needed.
 */
export function TimelineGridLines({ rangeStart, rangeEnd }) {
  const ticks = getTimelineTicks(rangeStart, rangeEnd);

  return (
    <div className="pointer-events-none absolute inset-0">
      {ticks.map((tick) => (
        <div key={tick.date} className="absolute inset-y-0 w-px bg-gray-100" style={{ left: `${tick.percent}%` }} />
      ))}
    </div>
  );
}

/**
 * Single row's bar: gray once the row has ended (`endDate` set), blue while
 * still ongoing (`endDate` null).
 */
export function TimelineBar({ rangeStart, rangeEnd, startDate, endDate }) {
  if (!startDate) {
    return null;
  }

  const totalDays = Math.max(diffDays(rangeEnd, rangeStart), 1);
  const isActive = !endDate;

  const clampedStart = clampIso(startDate, rangeStart, rangeEnd);
  const clampedEnd = clampIso(isActive ? rangeEnd : endDate, rangeStart, rangeEnd);

  const leftPercent = (diffDays(clampedStart, rangeStart) / totalDays) * 100;
  const widthPercent = Math.max(
    (diffDays(clampedEnd, clampedStart) / totalDays) * 100,
    MIN_BAR_WIDTH_PERCENT
  );

  return (
    <div
      className={`h-2.5 rounded-full ${isActive ? 'bg-blue-500' : 'bg-gray-300'}`}
      style={{ marginLeft: `${leftPercent}%`, width: `${widthPercent}%`, minWidth: '6px' }}
      title={`${startDate} → ${endDate ?? 'hiện tại'}`}
    />
  );
}

/**
 * Two-region scroll shell: a table wrapper that scrolls horizontally as one
 * piece when its content (the fixed-width left column plus the timeline)
 * doesn't fit. `dimmed` mirrors a "refreshing" state some list hooks expose.
 */
export function TimelineTable({ children, dimmed = false, minWidthClassName = 'min-w-[640px]' }) {
  return (
    <div className="overflow-x-auto rounded-md border border-gray-200">
      <div className={`${minWidthClassName} transition-opacity duration-150 ${dimmed ? 'opacity-50' : 'opacity-100'}`}>
        {children}
      </div>
    </div>
  );
}

/**
 * Header for a `TimelineTable`: a pinned label over the left column and the
 * date ticks over the timeline column, both scrolling as one unit.
 */
export function TimelineTableHeader({ label, rangeStart, rangeEnd, columnClassName = 'w-56' }) {
  return (
    <div className="flex items-center border-b border-gray-200 bg-gray-50">
      <div
        className={`sticky left-0 z-10 shrink-0 border-r border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold tracking-wide text-gray-500 uppercase ${columnClassName}`}
      >
        {label}
      </div>
      <div className="flex-1 px-3 py-2">
        <TimelineHeader rangeStart={rangeStart} rangeEnd={rangeEnd} />
      </div>
    </div>
  );
}

/**
 * One row of a `TimelineTable`: arbitrary content (name, badges, actions...)
 * in a column pinned via `sticky left-0` - so it never scrolls away with the
 * timeline even though both share one scroll container - next to that row's
 * bar. Flexbox stretches both to the same height automatically, so the two
 * regions always stay row-synced regardless of how tall `children` gets.
 */
export function TimelineRow({ children, rangeStart, rangeEnd, startDate, endDate, columnClassName = 'w-56' }) {
  return (
    <li className="flex">
      <div className={`sticky left-0 z-10 shrink-0 border-r border-gray-200 bg-white px-3 py-3 ${columnClassName}`}>
        {children}
      </div>

      <div className="relative flex flex-1 items-center px-3">
        <TimelineGridLines rangeStart={rangeStart} rangeEnd={rangeEnd} />
        <TimelineBar rangeStart={rangeStart} rangeEnd={rangeEnd} startDate={startDate} endDate={endDate} />
      </div>
    </li>
  );
}
