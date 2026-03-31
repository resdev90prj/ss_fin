import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const navigationItems = [
  { to: '/', label: 'Dashboard' },
  { to: '/accounts', label: 'Contas' },
  { to: '/categories', label: 'Categorias' },
  { to: '/transactions', label: 'Transacoes' },
  { to: '/targets', label: 'Execucao' },
  { to: '/agenda', label: 'Agenda' },
];

export default function AppShell() {
  const { session, logout } = useAuth();
  const currentUser = session.user;
  const scope = session.scope || {};

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand-box">
          <span className="brand-box__eyebrow">Parallel release</span>
          <h1>{session.release?.app_name || 'SaaS IA Finan'}</h1>
          <p>Frontend React rodando em paralelo ao legado PHP.</p>
        </div>

        <nav className="nav-list" aria-label="Principal">
          {navigationItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) => `nav-item ${isActive ? 'nav-item--active' : ''}`}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-note">
          <strong>Backend preservado</strong>
          <p>Permissoes, sessao, user_id e validacoes continuam no PHP existente.</p>
        </div>
      </aside>

      <div className="workspace">
        <header className="topbar">
          <div>
            <span className="topbar__eyebrow">New release / pilot control</span>
            <h2>Validacao paralela em producao</h2>
          </div>

          <div className="topbar__actions">
            <div className="profile-pill">
              <strong>{currentUser?.name || 'Usuario'}</strong>
              <span>
                {currentUser?.role === 'admin' ? 'Admin' : 'User'}
                {scope.scoped_user_id ? ` | Escopo ${scope.current_user_id}` : ''}
              </span>
            </div>

            <a className="ghost-button" href={session.release?.legacy_base || '/'} target="_self" rel="noreferrer">
              Abrir legado
            </a>

            <button className="solid-button" type="button" onClick={() => logout()}>
              Sair
            </button>
          </div>
        </header>

        <main className="main-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
