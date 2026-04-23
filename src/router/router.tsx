import { createBrowserRouter } from 'react-router-dom';
import LoginPage from '../modules/auth/pages/LoginPage';
import DashboardPage from '../modules/dashboard/pages/DashboardPage';
import PlantillasPage from '../modules/plantillas/pages/PlantillasPage';
import NuevaEvaluacionPage from '../modules/evaluacion/pages/NuevaEvaluacionPage';
import HistorialPage from '../modules/evaluacion/pages/HistorialPage';
import UsersPage from '../modules/users/pages/UsersPage';
import ConfiguracionPage from '../modules/configuracion/pages/ConfiguracionPage';
import MainLayout from '../layouts/MainLayout';
import { ProtectedRoute } from '../components/auth/ProtectedRoute';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <LoginPage />,
  },
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/app',
    element: (
      <ProtectedRoute>
        <MainLayout />
      </ProtectedRoute>
    ),
    children: [
      {
        path: 'dashboard',
        element: <DashboardPage />,
      },
      {
        path: 'plantillas',
        element: <PlantillasPage />,
      },
      {
        path: 'evaluacion',
        element: <NuevaEvaluacionPage />,
      },
      {
        path: 'historial',
        element: <HistorialPage />,
      },
      {
        path: 'usuarios',
        element: <UsersPage />,
      },
      {
        path: 'configuracion',
        element: <ConfiguracionPage />,
      },
    ],
  },
  {
    path: '*',
    element: <div className="flex h-screen items-center justify-center">404 - Not Found</div>,
  },
]);
