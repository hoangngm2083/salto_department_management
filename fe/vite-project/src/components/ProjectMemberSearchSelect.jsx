import { useEffect, useRef, useState } from 'react';
import { searchProjectMembers } from '../api/projectMembers';

/**
 * Debounced type-ahead for picking one project member (e.g. `assigned_to`
 * on a task), scoped to `GET /projects/{slug}/members` - a project's active
 * assignees, not every employee (that's what `EmployeeSearchSelect` is for).
 * Display line per plan mục 5.1: name bold, `level, roles...` dimmed below.
 */
export default function ProjectMemberSearchSelect({
  slug,
  value,
  valueLabel,
  onChange,
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
      searchProjectMembers(slug, { name: query || undefined, per_page: 10 })
        .then((res) => setOptions(res.data))
        .catch(() => {})
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(timeout);
  }, [query, open, slug]);

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);

    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  function memberSubline(member) {
    return [member.level, ...(member.roles ?? [])].filter(Boolean).join(', ');
  }

  function handleSelect(member) {
    onChange(member.id, member.name);
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
            <div className="px-3 py-2 text-sm text-gray-500">Không tìm thấy thành viên.</div>
          )}

          {!loading &&
            options.map((member) => (
              <button
                key={member.id}
                type="button"
                onClick={() => handleSelect(member)}
                className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100"
              >
                <span className="font-medium text-gray-900">{member.name}</span>
                {memberSubline(member) && (
                  <span className="block text-xs text-gray-500">{memberSubline(member)}</span>
                )}
              </button>
            ))}
        </div>
      )}
    </div>
  );
}
