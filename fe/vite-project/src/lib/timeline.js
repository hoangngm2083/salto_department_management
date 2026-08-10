export const MIN_BAR_WIDTH_PERCENT = 2;

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

/** Whole-day difference between two ISO date strings (`laterIso - earlierIso`). */
export function diffDays(laterIso, earlierIso) {
  return (Date.parse(laterIso) - Date.parse(earlierIso)) / 86_400_000;
}

/** Clamps an ISO date string into `[minIso, maxIso]` (lexicographic = chronological for ISO dates). */
export function clampIso(dateIso, minIso, maxIso) {
  if (dateIso < minIso) {
    return minIso;
  }

  if (dateIso > maxIso) {
    return maxIso;
  }

  return dateIso;
}

/** Adds a (possibly fractional, rounded) number of days to an ISO date string. */
export function addDaysIso(iso, days) {
  const date = new Date(`${iso}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + Math.round(days));

  return date.toISOString().slice(0, 10);
}

/**
 * Evenly spaced date ticks spanning `[rangeStart, rangeEnd]`, each with the
 * ISO date and its left-offset percent. Shared by the timeline header labels
 * and the vertical grid lines behind the rows, so both line up exactly.
 */
export function getTimelineTicks(rangeStart, rangeEnd, steps = 4) {
  const totalDays = Math.max(diffDays(rangeEnd, rangeStart), 1);

  return Array.from({ length: steps + 1 }, (_, i) => ({
    date: addDaysIso(rangeStart, (totalDays * i) / steps),
    percent: (i / steps) * 100,
  }));
}

/**
 * Resolves the [start, end] window (ISO date strings) a Timeline draws
 * against: `boundStart`/`boundEnd` when given (e.g. a project's own
 * start/end date), falling back to the earliest `start_date` among
 * `periods` and to today - an open-ended range (no known end yet) uses
 * "now" as the right edge.
 *
 * @param {object} options
 * @param {string|null} [options.boundStart] Explicit lower bound, if known.
 * @param {string|null} [options.boundEnd] Explicit upper bound, if known.
 * @param {Array<{ start_date?: string|null }>} [options.periods] Rows the
 *   timeline is about to render, used only as a fallback for `boundStart`.
 */
export function getTimelineRange({ boundStart, boundEnd, periods = [] } = {}) {
  const today = todayIso();
  const earliestPeriodStart = periods
    .map((period) => period.start_date)
    .filter(Boolean)
    .sort()[0];

  const start = boundStart ?? earliestPeriodStart ?? today;
  const end = boundEnd ?? today;

  return { start, end: end < start ? start : end };
}
