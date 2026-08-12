import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_PAGE_SIZE = Number(import.meta.env.VITE_PAGE_SIZE) || 15;

/**
 * Cursor-paginated list state shared by the table pages.
 *
 * Only the very first load may blank the table: every later fetch keeps the
 * current rows mounted and raises `refreshing` instead, so the table never
 * collapses to a single placeholder row and makes the page jump.
 *
 * @template T
 * @param {object} options
 * @param {(params: Record<string, unknown>) => Promise<{ data: T[], meta: Record<string, unknown> }>} options.fetcher
 *   Resource client to page through, e.g. `listDepartments`.
 * @param {Record<string, unknown>} options.params
 *   Query params for the fetcher. Changing any of them refetches from page one.
 * @param {number} [options.pageSize]
 *   Rows per page, sent to the fetcher as `per_page`. Defaults to `VITE_PAGE_SIZE`.
 * @param {number} [options.debounceMs]
 *   Delay before refetching after a params change, for filters typed into an input.
 * @param {(item: T) => boolean} [options.belongsInList]
 *   Whether an item still matches the active filters. Used by `replaceItem` to
 *   drop a record whose update moved it out of the current view.
 * @returns {{
 *   items: T[],
 *   meta: Record<string, unknown>,
 *   loading: boolean,
 *   refreshing: boolean,
 *   goToNext: () => void,
 *   goToPrev: () => void,
 *   refresh: () => void,
 *   replaceItem: (item: T) => void,
 *   removeItem: (id: number | string) => void,
 * }}
 */
export default function useCursorList({
  fetcher,
  params,
  pageSize = DEFAULT_PAGE_SIZE,
  debounceMs = 0,
  belongsInList,
}) {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const cursorRef = useRef(null);
  const hasLoadedRef = useRef(false);
  const requestIdRef = useRef(0);

  /** Keeps `fetchPage` stable without letting it read stale props. */
  const latestRef = useRef({ fetcher, params, pageSize });

  useEffect(() => {
    latestRef.current = { fetcher, params, pageSize };
  });

  const fetchPage = useCallback((nextCursor) => {
    const requestId = requestIdRef.current + 1;

    requestIdRef.current = requestId;
    cursorRef.current = nextCursor ?? null;

    if (hasLoadedRef.current) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    const { fetcher: fetchItems, params: query, pageSize: perPage } = latestRef.current;

    fetchItems({ ...query, per_page: perPage, cursor: nextCursor ?? undefined })
      .then((res) => {
        // A slow response must never overwrite the rows of a newer request.
        if (requestIdRef.current !== requestId) {
          return;
        }

        setItems(res.data);
        setMeta(res.meta);
      })
      .finally(() => {
        if (requestIdRef.current !== requestId) {
          return;
        }

        hasLoadedRef.current = true;
        setLoading(false);
        setRefreshing(false);
      });
  }, []);

  const paramsKey = JSON.stringify(params);

  useEffect(() => {
    const timeout = setTimeout(() => fetchPage(null), debounceMs);

    return () => clearTimeout(timeout);
  }, [paramsKey, pageSize, debounceMs, fetchPage]);

  function goToNext() {
    fetchPage(meta.next_cursor);
  }

  function goToPrev() {
    fetchPage(meta.prev_cursor);
  }

  /** Reloads the page currently on screen, e.g. after an import or a create. */
  function refresh() {
    fetchPage(cursorRef.current);
  }

  /**
   * Patches a record already on screen from a mutation response instead of
   * refetching: a round-trip would only make the table blink for data we hold.
   */
  function replaceItem(updated) {
    setItems((prev) =>
      prev.flatMap((item) => {
        if (item.id !== updated.id) {
          return [item];
        }

        return !belongsInList || belongsInList(updated) ? [updated] : [];
      })
    );
  }

  function removeItem(id) {
    setItems((prev) => prev.filter((item) => item.id !== id));
  }

  return {
    items,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    refresh,
    replaceItem,
    removeItem,
  };
}
