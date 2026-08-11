import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Single-fetch resource state for one dashboard widget.
 *
 * Each widget owns its own hook call, so its state (and its "làm mới" button)
 * is isolated from every other widget on the page - refreshing one never
 * touches another's data or triggers a full-page reload (plan mục 9.1).
 *
 * Mirrors `useCursorList`'s loading/refreshing split and `requestIdRef`
 * staleness guard, minus cursor pagination.
 *
 * @template T
 * @param {object} options
 * @param {() => Promise<T>} options.fetcher
 * @param {unknown[]} [options.deps]
 *   Changing any of these (compared by JSON.stringify, like useCursorList's
 *   params) refetches from scratch. Defaults to a stable empty array, i.e.
 *   fetch once on mount.
 * @returns {{
 *   data: T | undefined,
 *   loading: boolean,
 *   refreshing: boolean,
 *   error: boolean,
 *   refresh: () => void,
 * }}
 */
export default function useAsyncResource({ fetcher, deps = [] }) {
  const [data, setData] = useState(undefined);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(false);

  const hasLoadedRef = useRef(false);
  const requestIdRef = useRef(0);

  /** Keeps `load` stable without letting it read a stale `fetcher`. */
  const latestFetcherRef = useRef(fetcher);

  useEffect(() => {
    latestFetcherRef.current = fetcher;
  });

  const load = useCallback(() => {
    const requestId = requestIdRef.current + 1;

    requestIdRef.current = requestId;

    if (hasLoadedRef.current) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    latestFetcherRef
      .current()
      .then((result) => {
        // A slow response must never overwrite the data of a newer request.
        if (requestIdRef.current !== requestId) {
          return;
        }

        setData(result);
        setError(false);
      })
      .catch(() => {
        if (requestIdRef.current !== requestId) {
          return;
        }

        setError(true);
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

  const depsKey = JSON.stringify(deps);

  useEffect(() => {
    load();
  }, [depsKey, load]);

  return { data, loading, refreshing, error, refresh: load };
}
