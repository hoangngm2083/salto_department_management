import { clampIso, diffDays, getTimelineTicks, MIN_BAR_WIDTH_PERCENT, todayIso } from '../lib/timeline';

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
            // Index, not tick.date: a narrow zoomed window can round two
            // adjacent ticks to the same calendar date, which duplicated
            // this key when it was tick.date.
            key={i}
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
      {ticks.map((tick, i) => (
        <div key={i} className="absolute inset-y-0 w-px bg-gray-100" style={{ left: `${tick.percent}%` }} />
      ))}
    </div>
  );
}

/**
 * Single row's bar: gray once the row has ended (`endDate` set), blue while
 * still ongoing (`endDate` null). An ongoing row's bar stops at today, not at
 * `rangeEnd` - `rangeEnd` can sit in the future (e.g. a project's planned end
 * date), and filling all the way to it would draw activity that hasn't
 * happened yet.
 */
export function TimelineBar({ rangeStart, rangeEnd, startDate, endDate }) {
  if (!startDate) {
    return null;
  }

  const totalDays = Math.max(diffDays(rangeEnd, rangeStart), 1);
  const isActive = !endDate;

  const clampedStart = clampIso(startDate, rangeStart, rangeEnd);
  const clampedEnd = clampIso(isActive ? todayIso() : endDate, rangeStart, rangeEnd);

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
 * Two-region shell: a fixed-width left column plus the timeline, always
 * filling 100% of its container - the bars are already percentage-based, so
 * nothing needs a minimum pixel width (previously forced one, which is what
 * caused the horizontal scrollbar this is meant to avoid; use
 * `TimelineZoomControls` instead to see a narrower date window). `dimmed`
 * mirrors a "refreshing" state some list hooks expose.
 */
export function TimelineTable({ children, dimmed = false }) {
  return (
    <div className="rounded-md border border-gray-200">
      <div className={`transition-opacity duration-150 ${dimmed ? 'opacity-50' : 'opacity-100'}`}>{children}</div>
    </div>
  );
}

const ZOOM_PRESETS = [
  { value: null, label: 'Toàn bộ' },
  { value: 6, label: '6 tháng' },
  { value: 3, label: '3 tháng' },
  { value: 1, label: '1 tháng' },
];

/**
 * Preset zoom buttons for a Timeline - narrows the displayed date window
 * instead of letting the timeline scroll horizontally. `value` is `null`
 * (full range) or a number of months; pair with `getZoomedRange` in
 * `lib/timeline` to compute the actual window to render.
 */
export function TimelineZoomControls({ value, onChange }) {
  return (
    <div className="inline-flex rounded-md border border-gray-200 p-0.5 text-xs">
      {ZOOM_PRESETS.map((preset) => (
        <button
          key={preset.label}
          type="button"
          onClick={() => onChange(preset.value)}
          className={`rounded px-2 py-1 font-medium ${
            value === preset.value ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-100'
          }`}
        >
          {preset.label}
        </button>
      ))}
    </div>
  );
}

/**
 * Header for a `TimelineTable`: a label over the left column and the date
 * ticks over the timeline column. The left column takes up 3/10 of the
 * table's width (`w-[30%]`, vs. the timeline's 7/10) so row content (name,
 * status, role tags) has more room than the old fixed `w-40 sm:w-48`.
 */
export function TimelineTableHeader({ label, rangeStart, rangeEnd, columnClassName = 'w-[30%]' }) {
  return (
    <div className="flex items-center border-b border-gray-200 bg-gray-50">
      <div
        className={`shrink-0 border-r border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold tracking-wide text-gray-500 uppercase ${columnClassName}`}
      >
        {label}
      </div>
      <div className="flex-1 px-3 py-1.5">
        <TimelineHeader rangeStart={rangeStart} rangeEnd={rangeEnd} />
      </div>
    </div>
  );
}

/**
 * One row of a `TimelineTable`: arbitrary content (name, badges, actions...)
 * in a fixed-width left column next to that row's bar. Flexbox stretches
 * both to the same height automatically, so the two regions always stay
 * row-synced regardless of how tall `children` gets.
 */
export function TimelineRow({ children, rangeStart, rangeEnd, startDate, endDate, columnClassName = 'w-[30%]' }) {
  return (
    <li className="flex">
      <div className={`shrink-0 border-r border-gray-200 bg-white px-3 py-2 ${columnClassName}`}>
        {children}
      </div>

      <div className="relative flex flex-1 items-center px-3">
        <TimelineGridLines rangeStart={rangeStart} rangeEnd={rangeEnd} />
        <TimelineBar rangeStart={rangeStart} rangeEnd={rangeEnd} startDate={startDate} endDate={endDate} />
      </div>
    </li>
  );
}
