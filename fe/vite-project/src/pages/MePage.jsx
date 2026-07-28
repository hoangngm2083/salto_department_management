import { useAuth } from '../context/useAuth';
import EmployeeProfileView from '../components/EmployeeProfileView';

export default function MePage() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return <EmployeeProfileView employee={user} canEdit />;
}
