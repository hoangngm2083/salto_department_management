import { Navigate, Route, Routes } from 'react-router-dom';
import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import { useAuth } from './context/useAuth';
import { roleHomePath } from './lib/role-redirect';
import LoginPage from './pages/LoginPage';
import MePage from './pages/MePage';
import EmployeeProfilePage from './pages/EmployeeProfilePage';
import DepartmentsListPage from './pages/DepartmentsListPage';
import DepartmentDetailPage from './pages/DepartmentDetailPage';

function HomeRedirect() {
  const { user } = useAuth();

  return <Navigate to={roleHomePath(user)} replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<Layout />}>
          <Route path="/" element={<HomeRedirect />} />
          <Route path="/me" element={<MePage />} />
          <Route path="/employees/:id" element={<EmployeeProfilePage />} />

          <Route element={<ProtectedRoute roles={['admin']} />}>
            <Route path="/departments" element={<DepartmentsListPage />} />
          </Route>

          <Route element={<ProtectedRoute roles={['admin', 'manager']} />}>
            <Route path="/departments/:slug" element={<DepartmentDetailPage />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
