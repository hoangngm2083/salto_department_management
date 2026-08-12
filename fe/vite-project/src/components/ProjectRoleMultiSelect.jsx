import { useEffect, useState } from 'react';
import { listProjectRoles } from '../api/projectRoles';

/**
 * Checkbox picker for project_role_ids, e.g. an assignment's initial roles.
 * project_roles is small master data (a handful of rows), so unlike
 * EmployeeMultiSelect this loads the full active list once instead of a
 * debounced search.
 */
export default function ProjectRoleMultiSelect({ selected, onChange }) {
  const [roles, setRoles] = useState([]);

  useEffect(() => {
    listProjectRoles({ status: 'active', per_page: 100 })
      .then((res) => setRoles(res.data))
      .catch(() => {});
  }, []);

  function toggle(roleId) {
    if (selected.includes(roleId)) {
      onChange(selected.filter((id) => id !== roleId));
    } else {
      onChange([...selected, roleId]);
    }
  }

  if (roles.length === 0) {
    return <p className="text-sm text-gray-500">Chưa có vai trò dự án nào đang active.</p>;
  }

  return (
    <div className="flex flex-wrap gap-2">
      {roles.map((role) => {
        const isSelected = selected.includes(role.id);

        return (
          <label
            key={role.id}
            className={`flex cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1 text-xs ${
              isSelected
                ? 'border-gray-900 bg-gray-900 text-white'
                : 'border-gray-300 text-gray-700'
            }`}
          >
            <input
              type="checkbox"
              checked={isSelected}
              onChange={() => toggle(role.id)}
              className="sr-only"
            />
            {role.name}
          </label>
        );
      })}
    </div>
  );
}
