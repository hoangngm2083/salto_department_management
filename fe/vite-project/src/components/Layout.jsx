import { useEffect, useRef } from 'react';
import { Outlet, useLocation, useNavigationType } from 'react-router-dom';
import Sidebar from './Sidebar';
import { pushNavHistory } from '../lib/nav-history';

export default function Layout() {
  const location = useLocation();
  const navigationType = useNavigationType();
  const previousPathRef = useRef(null);

  useEffect(() => {
    // Only a forward (PUSH) navigation means "the user went somewhere new" -
    // a POP (browser back/forward) or REPLACE (redirects, BackLink's own
    // navigation) shouldn't grow the stack.
    if (previousPathRef.current && navigationType === 'PUSH') {
      pushNavHistory(previousPathRef.current);
    }

    previousPathRef.current = location.pathname;
  }, [location.pathname, navigationType]);

  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar />
      <main className="flex-1 overflow-y-auto">
        <div className="px-6 py-8">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
