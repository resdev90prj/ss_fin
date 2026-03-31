import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import LoadingState from '../../components/LoadingState';
import { useAuth } from '../../context/AuthContext';

export default function LogoutPage() {
  const navigate = useNavigate();
  const { logout } = useAuth();

  useEffect(() => {
    let active = true;

    async function performLogout() {
      await logout();

      if (active) {
        navigate('/login', { replace: true });
      }
    }

    performLogout();

    return () => {
      active = false;
    };
  }, [logout, navigate]);

  return (
    <div className="fullscreen-center">
      <LoadingState title="Encerrando sessao" text="Limpando a sessao da nova release com seguranca." />
    </div>
  );
}
