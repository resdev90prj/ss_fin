import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import LoadingState from './LoadingState';

export default function ProtectedRoute() {
  const { loading, session } = useAuth();
  const location = useLocation();
  const shouldPreserveFrom = location.pathname !== '/login' && location.pathname !== '/logout';

  if (loading) {
    return (
      <div className="fullscreen-center">
        <LoadingState text="Validando sessao e contexto atual." />
      </div>
    );
  }

  if (!session.authenticated) {
    return <Navigate to="/login" replace state={shouldPreserveFrom ? { from: location.pathname } : null} />;
  }

  return <Outlet />;
}
