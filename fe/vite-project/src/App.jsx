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
import EmployeesListPage from './pages/EmployeesListPage';
import LeaveRequestsListPage from './pages/LeaveRequestsListPage';
import LevelsListPage from './pages/LevelsListPage';
import ProjectsListPage from './pages/ProjectsListPage';
import ProjectDetailPage from './pages/ProjectDetailPage';
import ProjectRolesListPage from './pages/ProjectRolesListPage';
import StatusPage from './pages/StatusPage';

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
          <Route path="/leave-requests" element={<LeaveRequestsListPage />} />
          <Route
            path="/403"
            element={
              <StatusPage
                code={403}
                title="Không có quyền truy cập"
                message="Bạn không có quyền xem nội dung này."
              />
            }
          />
          <Route
            path="/404"
            element={
              <StatusPage
                code={404}
                title="Không tìm thấy"
                message="Nội dung bạn tìm không tồn tại hoặc đã bị xoá."
              />
            }
          />

          <Route element={<ProtectedRoute roles={['admin']} />}>
            <Route path="/departments" element={<DepartmentsListPage />} />
          </Route>

          <Route element={<ProtectedRoute roles={['admin', 'manager']} />}>
            <Route path="/departments/:slug" element={<DepartmentDetailPage />} />
            <Route path="/employees" element={<EmployeesListPage />} />
            <Route path="/levels" element={<LevelsListPage />} />
            <Route path="/projects" element={<ProjectsListPage />} />
            <Route path="/projects/:slug" element={<ProjectDetailPage />} />
            <Route path="/project-roles" element={<ProjectRolesListPage />} />
          </Route>

          {/* Matches any unmatched URL under this pathless layout tree - ProtectedRoute
              above still gates it first, so an unauthenticated visitor is bounced to
              /login same as any other route; an authenticated one lands on 404. */}
          <Route path="*" element={<Navigate to="/404" replace />} />
        </Route>
      </Route>
    </Routes>
  );
}
