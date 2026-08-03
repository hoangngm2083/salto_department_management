import { useNavigate } from 'react-router-dom';
import { popNavHistory } from '../lib/nav-history';

/**
 * A "back" action that returns to wherever the user actually came from
 * (tracked by Layout.jsx into sessionStorage), falling back to `fallback`
 * when there's no recorded history (direct link, reload, stack exhausted).
 */
export default function BackLink({ fallback, className, children }) {
  const navigate = useNavigate();

  function handleClick() {
    const previous = popNavHistory();
    navigate(previous ?? fallback, { replace: true });
  }

  return (
    <button type="button" onClick={handleClick} className={className}>
      {children}
    </button>
  );
}
