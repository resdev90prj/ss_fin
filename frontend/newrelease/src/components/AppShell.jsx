import { useMemo, useState } from 'react';
import {
  Activity,
  ArrowUpRight,
  ChevronDown,
  ChevronRight,
  Menu,
} from 'lucide-react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { cn } from '../lib/utils';
import { getNavigationGroups, moduleRegistry } from '../navigation/menu';
import { Button } from './ui/button';

const defaultCollapsedGroups = {
  overview: false,
  finance: false,
  planning: false,
  operations: false,
  analysis: false,
  account: false,
  admin: false,
};

function routeIsActive(pathname, itemPath) {
  if (itemPath === '/') {
    return pathname === '/';
  }

  return pathname === itemPath || pathname.startsWith(`${itemPath}/`);
}

function statusPill(item) {
  if (item.status === 'bridge') {
    return (
      <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-amber-700">
        PHP
      </span>
    );
  }

  if (item.status === 'action') {
    return (
      <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-rose-700">
        Sessao
      </span>
    );
  }

  return (
    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">
      React
    </span>
  );
}

function NavigationItem({ item, onNavigate }) {
  const Icon = item.icon;

  return (
    <NavLink
      to={item.path}
      end={item.path === '/'}
      onClick={onNavigate}
      className={({ isActive }) =>
        cn(
          'group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm transition-all',
          isActive
            ? 'bg-white text-slate-950 shadow-lg shadow-black/20'
            : item.status === 'action'
              ? 'text-rose-200 hover:bg-rose-500/10 hover:text-white'
              : 'text-slate-300 hover:bg-white/8 hover:text-white',
        )
      }
    >
      {({ isActive }) => (
        <>
          <div
            className={cn(
              'flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl transition-colors',
              isActive
                ? 'bg-slate-950 text-white'
                : item.status === 'action'
                  ? 'bg-white/5 text-rose-200'
                  : 'bg-white/5 text-slate-200 group-hover:bg-white/10',
            )}
          >
            <Icon className="h-4 w-4" />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex items-center justify-between gap-3">
              <span className="truncate font-semibold">{item.label}</span>
              {statusPill(item)}
            </div>
            <p
              className={cn(
                'mt-1 truncate text-[11px] leading-5',
                isActive ? 'text-slate-500' : 'text-slate-400',
              )}
            >
              {item.shortLabel !== item.label ? item.shortLabel : item.description}
            </p>
          </div>
        </>
      )}
    </NavLink>
  );
}

function NavigationGroup({ group, pathname, collapsed, onToggle, onNavigate }) {
  const hasActiveItem = group.items.some((item) => routeIsActive(pathname, item.path));
  const isCollapsed = hasActiveItem ? false : collapsed;

  return (
    <section className="space-y-2">
      <button
        type="button"
        onClick={() => onToggle(group.key)}
        className="flex w-full items-center justify-between rounded-2xl px-2 py-2 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
      >
        <span>{group.label}</span>
        {isCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>

      {!isCollapsed ? (
        <div className="space-y-2">
          {group.items.map((item) => (
            <NavigationItem key={item.key} item={item} onNavigate={onNavigate} />
          ))}
        </div>
      ) : null}
    </section>
  );
}

export default function AppShell() {
  const { session } = useAuth();
  const location = useLocation();
  const currentUser = session.user;
  const scope = session.scope || {};
  const isAdmin = currentUser?.role === 'admin' || scope.is_admin;
  const pathname = location.pathname || '/';
  const [collapsedGroups, setCollapsedGroups] = useState(defaultCollapsedGroups);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navigationGroups = useMemo(() => getNavigationGroups({ isAdmin }), [isAdmin]);

  const currentModule = useMemo(() => {
    return Object.values(moduleRegistry).find((item) => routeIsActive(pathname, item.path)) || moduleRegistry.dashboard;
  }, [pathname]);

  function toggleGroup(groupKey) {
    setCollapsedGroups((current) => ({
      ...current,
      [groupKey]: !current[groupKey],
    }));
  }

  function handleNavigate() {
    setMobileMenuOpen(false);
  }

  return (
    <div className="app-shell bg-grid-slate">
      <aside className="hidden min-h-screen border-r border-slate-200/80 bg-slate-950 px-5 py-6 text-white lg:flex lg:flex-col">
        <div className="rounded-[28px] border border-white/10 bg-white/5 p-5">
          <span className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
            Parallel release
          </span>
          <h1 className="mt-4 font-display text-3xl tracking-tight">{session.release?.app_name || 'SaaS IA Finan'}</h1>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            Mapa completo do produto refletido na nova navegacao, sem esconder capacidades do legado.
          </p>
        </div>

        <div className="mt-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-slate-300">
          <Activity className="h-4 w-4 text-blue-300" />
          Migracao funcional em paralelo
        </div>

        <nav className="mt-6 flex-1 space-y-4 overflow-y-auto pr-1" aria-label="Principal">
          {navigationGroups.map((group) => (
            <NavigationGroup
              key={group.key}
              group={group}
              pathname={pathname}
              collapsed={Boolean(collapsedGroups[group.key])}
              onToggle={toggleGroup}
              onNavigate={handleNavigate}
            />
          ))}
        </nav>

        <div className="mt-6 rounded-[28px] border border-white/10 bg-white/5 p-5">
          <strong className="text-sm font-semibold text-white">Paridade primeiro</strong>
          <p className="mt-2 text-sm leading-6 text-slate-300">
            Modulos ainda nao migrados seguem no menu com rota propria e ponte clara para o PHP.
          </p>
        </div>
      </aside>

      <div className="workspace">
        <header className="border-b border-slate-200/80 bg-white/70 px-4 py-4 backdrop-blur md:px-6">
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
              <div className="flex items-start gap-3">
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  className="lg:hidden"
                  onClick={() => setMobileMenuOpen((current) => !current)}
                >
                  <Menu className="h-4 w-4" />
                </Button>

                <div>
                  <span className="hero-card__eyebrow">New release / full product map</span>
                  <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-slate-950">
                    {currentModule.label}
                  </h2>
                  <p className="mt-1 text-sm text-slate-600">
                    {currentModule.description}
                  </p>
                </div>
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
                    <ArrowUpRight className="h-4 w-4" />
                  </a>
                </Button>
              </div>
            </div>

            {mobileMenuOpen ? (
              <div className="rounded-[28px] border border-slate-200 bg-slate-950 p-4 text-white shadow-panel lg:hidden">
                <div className="space-y-4">
                  {navigationGroups.map((group) => (
                    <NavigationGroup
                      key={group.key}
                      group={group}
                      pathname={pathname}
                      collapsed={Boolean(collapsedGroups[group.key])}
                      onToggle={toggleGroup}
                      onNavigate={handleNavigate}
                    />
                  ))}
                </div>
              </div>
            ) : null}
          </div>
        </header>

        <main className="main-content px-4 py-6 md:px-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
