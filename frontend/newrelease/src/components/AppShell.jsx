import { useEffect, useMemo, useRef, useState } from 'react';
import {
  CirclePlay,
  ChevronDown,
  ChevronRight,
  Menu,
} from 'lucide-react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { cn } from '../lib/utils';
import { getNavigationGroups, moduleRegistry } from '../navigation/menu';
import { useOnboarding } from '../onboarding/OnboardingProvider';
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

function NavigationItem({ item, onNavigate }) {
  const Icon = item.icon;

  return (
    <NavLink
      to={item.path}
      end={item.path === '/'}
      onClick={onNavigate}
      data-onboarding-id={`nav-${item.key}`}
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
  const { startTour } = useOnboarding();
  const location = useLocation();
  const currentUser = session.user;
  const scope = session.scope || {};
  const isAdmin = currentUser?.role === 'admin' || scope.is_admin;
  const pathname = location.pathname || '/';
  const [collapsedGroups, setCollapsedGroups] = useState(defaultCollapsedGroups);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const mobileMenuOpenedByTourRef = useRef(false);

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

  useEffect(() => {
    function handleTourOpen() {
      setCollapsedGroups({ ...defaultCollapsedGroups });

      if (window.innerWidth < 1024) {
        setMobileMenuOpen((current) => {
          mobileMenuOpenedByTourRef.current = !current;
          return true;
        });
        return;
      }

      mobileMenuOpenedByTourRef.current = false;
    }

    function handleTourClose() {
      if (mobileMenuOpenedByTourRef.current) {
        setMobileMenuOpen(false);
      }

      mobileMenuOpenedByTourRef.current = false;
    }

    window.addEventListener('onboarding:tour-open', handleTourOpen);
    window.addEventListener('onboarding:tour-close', handleTourClose);

    return () => {
      window.removeEventListener('onboarding:tour-open', handleTourOpen);
      window.removeEventListener('onboarding:tour-close', handleTourClose);
    };
  }, []);

  return (
    <div className="app-shell bg-grid-slate">
      <aside className="hidden min-h-screen border-r border-slate-200/80 bg-slate-950 px-5 py-6 text-white lg:flex lg:flex-col">
        <div className="rounded-[28px] border border-white/10 bg-white/5 p-5">
          <span className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
            Gestao financeira
          </span>
          <h1 className="mt-4 font-display text-3xl tracking-tight">
            {currentUser?.name || session.release?.app_name || 'SaaS IA Finan'}
          </h1>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            Acompanhe caixa, metas, agenda e operacao em uma experiencia clara e objetiva.
          </p>
        </div>

        <nav className="mt-6 flex-1 space-y-4 overflow-y-auto pr-1" aria-label="Principal" data-onboarding-id="main-navigation">
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
                  <span className="hero-card__eyebrow">Painel</span>
                  <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-slate-950">
                    {currentModule.label}
                  </h2>
                  <p className="mt-1 text-sm text-slate-600">
                    {currentModule.description}
                  </p>
                </div>
              </div>

              <div className="flex flex-col gap-3 md:flex-row md:items-center">
                <Button type="button" variant="outline" size="sm" className="bg-white" onClick={() => startTour(0)}>
                  <CirclePlay className="h-4 w-4" />
                  Tour
                </Button>

                <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                  <strong className="block text-sm font-semibold text-slate-950">{currentUser?.name || 'Usuario'}</strong>
                  <span className="text-xs text-slate-500">
                    {currentUser?.role === 'admin' ? 'Administrador' : 'Usuario'}
                    {scope.scoped_user_id ? ` | Visao ${scope.scoped_user_id}` : ''}
                  </span>
                </div>
              </div>
            </div>

            {mobileMenuOpen ? (
              <div className="rounded-[28px] border border-slate-200 bg-slate-950 p-4 text-white shadow-panel lg:hidden" data-onboarding-id="main-navigation">
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
