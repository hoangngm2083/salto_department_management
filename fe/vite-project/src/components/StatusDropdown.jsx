import { useEffect, useRef, useState } from 'react';

/**
 * Single-column status control for the two "duyệt request" list pages
 * (leave requests, task delay requests): replaces the old separate
 * Status + Hành động columns. Renders as a plain badge when the current
 * actor has no `options` to transition into (read-only, same look as
 * before); otherwise the badge becomes a dropdown trigger listing the
 * current status (marked, not selectable) alongside every status `options`
 * allows, each of them showing this same confirm-then-submit flow.
 */
export default function StatusDropdown({ status, statusLabels, statusBadgeClasses, options, busy, onSelect }) {
  const [open, setOpen] = useState(false);
  const containerRef = useRef(null);

  const canAct = options.length > 0;

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);

    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [open]);

  function handlePick(value) {
    setOpen(false);

    if (value === status) {
      return;
    }

    if (window.confirm(`Đổi trạng thái sang "${statusLabels[value] ?? value}"?`)) {
      onSelect(value);
    }
  }

  const menuValues = [status, ...options.filter((value) => value !== status)];

  return (
    <div className="relative inline-block" ref={containerRef}>
      <button
        type="button"
        disabled={!canAct || busy}
        onClick={() => setOpen((o) => !o)}
        className={`rounded-full px-2 py-0.5 text-xs font-medium disabled:opacity-50 ${
          statusBadgeClasses[status] ?? 'bg-gray-100 text-gray-700'
        } ${canAct ? 'cursor-pointer hover:opacity-80' : 'cursor-default'}`}
      >
        {statusLabels[status] ?? status}
        {canAct && <span className="ml-1 align-middle text-[9px]">&#9662;</span>}
      </button>

      {open && canAct && (
        <div className="absolute z-10 mt-1 w-44 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg">
          {menuValues.map((value) => {
            const isCurrent = value === status;

            return (
              <button
                key={value}
                type="button"
                disabled={isCurrent}
                onClick={() => handlePick(value)}
                className={`block w-full px-3 py-1.5 text-left text-xs ${
                  isCurrent ? 'cursor-not-allowed bg-gray-50 text-gray-400' : 'text-gray-700 hover:bg-gray-100'
                }`}
              >
                {statusLabels[value] ?? value}
                {isCurrent && ' (hiện tại)'}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
