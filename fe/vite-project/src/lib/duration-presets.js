export const DURATION_PRESETS = [
  { label: '1 tháng', months: 1 },
  { label: '2 tháng', months: 2 },
  { label: '3 tháng', months: 3 },
  { label: '6 tháng', months: 6 },
];

export function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Adds a whole number of calendar months to an ISO date string, clamping the
 * day into the target month (e.g. 2026-01-31 + 1 month -> 2026-02-28) instead
 * of overflowing into the month after, like native `Date` does.
 */
export function addMonthsIso(iso, months) {
  const [year, month, day] = iso.split('-').map(Number);
  const targetMonthIndex = month - 1 + months;
  const daysInTargetMonth = new Date(Date.UTC(year, targetMonthIndex + 1, 0)).getUTCDate();

  return new Date(Date.UTC(year, targetMonthIndex, Math.min(day, daysInTargetMonth))).toISOString().slice(0, 10);
}
