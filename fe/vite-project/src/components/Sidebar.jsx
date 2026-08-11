import { useEffect, useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import {
  ArrowLeftOnRectangleIcon,
  ChevronDoubleLeftIcon,
  ChevronDoubleRightIcon,
  UserCircleIcon,
} from '@heroicons/react/24/outline';
import { useAuth } from '../context/useAuth';
import { beginLogoutGuard } from '../lib/auth-storage';
import { ROLE_LABELS } from '../lib/role-labels';
import { sidebarNavItems } from '../lib/role-redirect';
import NotificationBell from './NotificationBell';

const COLLAPSE_STORAGE_KEY = 'sidebar:collapsed';

export default function Sidebar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const navItems = sidebarNavItems(user);

  const [collapsed, setCollapsed] = useState(() => localStorage.getItem(COLLAPSE_STORAGE_KEY) === 'true');

  useEffect(() => {
    localStorage.setItem(COLLAPSE_STORAGE_KEY, String(collapsed));
  }, [collapsed]);

  async function handleLogout() {
    beginLogoutGuard();

    try {
      await logout();
    } finally {
      // The guard is lifted once LoginPage actually mounts (see its effect),
      // not here - React flushes ProtectedRoute's transitional re-render
      // (still against the old route) later than this line runs.
      navigate('/login', { replace: true });
    }
  }

  return (
    <aside
      className={`flex h-screen shrink-0 flex-col border-r border-gray-200 bg-white transition-[width] duration-150 ${
        collapsed ? 'w-16' : 'w-60'
      }`}
    >
      <div
        className={`flex items-center border-b border-gray-200 px-4 py-3 ${collapsed ? 'justify-center' : 'justify-between'}`}
      >
        {!collapsed && <span className="truncate text-base font-semibold text-gray-900">Quản lý dự án</span>}
        <button
          type="button"
          onClick={() => setCollapsed((c) => !c)}
          className="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          aria-label={collapsed ? 'Mở rộng menu' : 'Thu gọn menu'}
        >
          {collapsed ? <ChevronDoubleRightIcon className="size-5" /> : <ChevronDoubleLeftIcon className="size-5" />}
        </button>
      </div>

      {navItems.length > 0 && (
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-2">
          {navItems.map((item) => {
            const Icon = item.icon;

            return (
              <NavLink
                key={item.to}
                to={item.to}
                title={collapsed ? item.label : undefined}
                className={({ isActive }) =>
                  `flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium ${collapsed ? 'justify-center' : ''} ${
                    isActive ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                  }`
                }
              >
                <Icon className="size-5 shrink-0" />
                {!collapsed && <span className="truncate">{item.label}</span>}
              </NavLink>
            );
          })}
        </nav>
      )}

      {user && (
        <div className={`border-t border-gray-200 p-2 ${collapsed ? 'flex flex-col items-center gap-2' : 'space-y-2'}`}>
          <div className={collapsed ? '' : 'flex justify-end'}>
            <NotificationBell />
          </div>

          <NavLink
            to={`/employees/${user.id}`}
            title={collapsed ? 'Hồ sơ của tôi' : undefined}
            className={({ isActive }) =>
              `flex items-center gap-2 rounded-md px-2 py-1.5 text-sm ${collapsed ? 'justify-center' : ''} ${
                isActive ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'
              }`
            }
          >
            <UserCircleIcon className="size-6 shrink-0" />
            {!collapsed && (
              <span className="min-w-0 flex-1 truncate">
                {user.name}{' '}
                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                  {ROLE_LABELS[user.position] ?? user.position}
                </span>
              </span>
            )}
          </NavLink>

          <button
            type="button"
            onClick={handleLogout}
            title={collapsed ? 'Đăng xuất' : undefined}
            className={`flex items-center gap-2 rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 ${
              collapsed ? 'justify-center' : 'w-full'
            }`}
          >
            <ArrowLeftOnRectangleIcon className="size-4 shrink-0" />
            {!collapsed && 'Đăng xuất'}
          </button>
        </div>
      )}
    </aside>
  );
}
