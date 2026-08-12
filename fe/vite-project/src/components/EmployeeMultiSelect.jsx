import { useEffect, useRef, useState } from 'react';
import { listEmployees } from '../api/employees';

/**
 * Debounced type-ahead for picking multiple employees, e.g. a project's
 * initial `manager_employee_ids`. Selections render as removable chips above
 * the search box; already-picked employees are filtered out of the dropdown.
 */
export default function EmployeeMultiSelect({ selected, onChange, placeholder = 'Tìm theo tên...' }) {
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
        .then((res) => setOptions(res.data))
        .catch(() => {})
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(timeout);
  }, [query, open]);

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);

    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const selectedIds = selected.map((employee) => employee.id);
  const visibleOptions = options.filter((employee) => !selectedIds.includes(employee.id));

  function handleSelect(employee) {
    onChange([...selected, { id: employee.id, name: employee.name }]);
    setQuery('');
  }

  function handleRemove(id) {
    onChange(selected.filter((employee) => employee.id !== id));
  }

  return (
    <div>
      {selected.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-2">
          {selected.map((employee) => (
            <span
              key={employee.id}
              className="flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
            >
              {employee.name}
              <button
                type="button"
                onClick={() => handleRemove(employee.id)}
                className="text-gray-400 hover:text-gray-600"
                aria-label={`Bỏ chọn ${employee.name}`}
              >
                &times;
              </button>
            </span>
          ))}
        </div>
      )}

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

            {!loading && visibleOptions.length === 0 && (
              <div className="px-3 py-2 text-sm text-gray-500">Không tìm thấy nhân viên.</div>
            )}

            {!loading &&
              visibleOptions.map((employee) => (
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
    </div>
  );
}
