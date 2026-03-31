import {
  Activity,
  BriefcaseBusiness,
  CalendarClock,
  LayoutDashboard,
  LogOut,
  Rocket,
  Shapes,
  WalletCards,
} from 'lucide-react';
import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { cn } from '../lib/utils';
import { Button } from './ui/button';

const navigationItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/accounts', label: 'Contas', icon: WalletCards },
  { to: '/categories', label: 'Categorias', icon: Shapes },
  { to: '/transactions', label: 'Transacoes', icon: BriefcaseBusiness },
  { to: '/targets', label: 'Execucao', icon: Rocket },
  { to: '/agenda', label: 'Agenda', icon: CalendarClock },
];

export default function AppShell() {
  const { session, logout } = useAuth();
  const currentUser = session.user;
  const scope = session.scope || {};

  return (
    <div className="app-shell bg-grid-slate">
      <aside className="hidden min-h-screen border-r border-slate-200/80 bg-slate-950 px-5 py-6 text-white lg:flex lg:flex-col">
        <div className="rounded-[28px] border border-white/10 bg-white/5 p-5">
          <span className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
            Parallel release
          </span>
          <h1 className="mt-4 font-display text-3xl tracking-tight">{session.release?.app_name || 'SaaS IA Finan'}</h1>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            Novo cockpit visual em React, conectado ao mesmo backend PHP que sustenta a operacao atual.
          </p>
        </div>

        <div className="mt-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-slate-300">
          <Activity className="h-4 w-4 text-blue-300" />
          Validacao paralela ativa
        </div>

        <nav className="mt-6 flex flex-1 flex-col gap-2" aria-label="Principal">
          {navigationItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) =>
                cn(
                  'group flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-300 transition-all hover:bg-white/8 hover:text-white',
                  isActive && 'bg-white text-slate-950 shadow-lg shadow-black/20',
                )
              }
            >
              <item.icon className="h-4 w-4" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="rounded-[28px] border border-white/10 bg-white/5 p-5">
          <strong className="text-sm font-semibold text-white">Backend preservado</strong>
          <p className="mt-2 text-sm leading-6 text-slate-300">
            Permissoes, sessao, user_id e validacoes continuam no PHP existente.
          </p>
        </div>
      </aside>

      <div className="workspace">
        <header className="border-b border-slate-200/80 bg-white/70 px-4 py-4 backdrop-blur md:px-6">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <span className="hero-card__eyebrow">New release / pilot control</span>
              <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-slate-950">
                Dashboard financeiro com nova camada de UX
              </h2>
              <p className="mt-1 text-sm text-slate-600">
                A operacao continua no PHP. Aqui estamos validando a experiencia nova com mais clareza visual.
              </p>
            </div>

            <div className="flex flex-col gap-3 md:flex-row md:items-center">
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <strong className="block text-sm font-semibold text-slate-950">{currentUser?.name || 'Usuario'}</strong>
                <span className="text-xs text-slate-500">
                  {currentUser?.role === 'admin' ? 'Admin' : 'User'}
                  {scope.scoped_user_id ? ` | Escopo ${scope.current_user_id}` : ''}
                </span>
              </div>

              <Button asChild variant="outline">
                <a href={session.release?.legacy_base || '/'} target="_self" rel="noreferrer">
                  Abrir legado
                </a>
              </Button>

              <Button type="button" variant="ghost" onClick={() => logout()}>
                <LogOut className="h-4 w-4" />
                Sair
              </Button>
            </div>
          </div>
        </header>

        <main className="main-content px-4 py-6 md:px-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
