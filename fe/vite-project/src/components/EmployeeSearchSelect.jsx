import { useEffect, useRef, useState } from 'react';
import { listEmployees } from '../api/employees';

/**
 * Debounced type-ahead for picking a single employee by name, e.g. assigning
 * `manager_employee_id`. Renders the selection as a chip with a clear button
 * once picked, matching the plain <select> fields it sits alongside.
 */
export default function EmployeeSearchSelect({
  value,
  valueLabel,
  onChange,
  excludeId,
  placeholder = 'Tìm theo tên...',
}) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [options, setOptions] = useState([]);
  const [loading, setLoading] = useState(false);
  const containerRef = useRef(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setLoading(true);

    const timeout = setTimeout(() => {
      listEmployees({ name: query || undefined, per_page: 10 })
        .then((res) => setOptions(res.data.filter((employee) => employee.id !== excludeId)))
        .catch(() => {})
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(timeout);
  }, [query, open, excludeId]);

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);

    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  function handleSelect(employee) {
    onChange(employee.id, employee.name);
    setQuery('');
    setOpen(false);
  }

  function handleClear() {
    onChange(null, null);
    setQuery('');
  }

  if (value) {
    return (
      <div className="flex items-center justify-between rounded-md border border-gray-300 px-3 py-2 text-sm">
        <span>{valueLabel}</span>
        <button
          type="button"
          onClick={handleClear}
          className="text-gray-400 hover:text-gray-600"
          aria-label="Bỏ chọn"
        >
          &times;
        </button>
      </div>
    );
  }

  return (
    <div className="relative" ref={containerRef}>
      <input
        type="text"
        value={query}
        placeholder={placeholder}
        onFocus={() => setOpen(true)}
        onChange={(e) => {
          setQuery(e.target.value);
          setOpen(true);
        }}
        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
      />

      {open && (
        <div className="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
          {loading && <div className="px-3 py-2 text-sm text-gray-500">Đang tìm...</div>}

          {!loading && options.length === 0 && (
            <div className="px-3 py-2 text-sm text-gray-500">Không tìm thấy nhân viên.</div>
          )}

          {!loading &&
            options.map((employee) => (
              <button
                key={employee.id}
                type="button"
                onClick={() => handleSelect(employee)}
                className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100"
              >
                {employee.name} <span className="text-gray-400">({employee.email})</span>
              </button>
            ))}
        </div>
      )}
    </div>
  );
}
