import {
  Archive,
  ArrowLeftRight,
  CalendarClock,
  CircleDollarSign,
  Goal,
  Landmark,
  LayoutDashboard,
  LogOut,
  PiggyBank,
  Rocket,
  Shapes,
  Upload,
  UserRound,
  Users,
  Vault,
  WalletCards,
} from 'lucide-react';

export const moduleRegistry = {
  dashboard: {
    key: 'dashboard',
    path: '/',
    label: 'Dashboard',
    shortLabel: 'Dashboard',
    group: 'overview',
    icon: LayoutDashboard,
    status: 'live',
    description: 'Resumo financeiro, alertas, agenda e score semanal.',
    legacyRoute: 'dashboard',
  },
  accounts: {
    key: 'accounts',
    path: '/accounts',
    label: 'Contas',
    shortLabel: 'Contas',
    group: 'finance',
    icon: WalletCards,
    status: 'live',
    description: 'Cadastro e status das contas financeiras do usuario.',
    legacyRoute: 'accounts',
  },
  boxes: {
    key: 'boxes',
    path: '/boxes',
    label: 'Caixas',
    shortLabel: 'Caixas',
    group: 'finance',
    icon: Vault,
    status: 'bridge',
    description: 'Caixas e vinculos operacionais ainda preservados no legado.',
    legacyRoute: 'boxes',
  },
  categories: {
    key: 'categories',
    path: '/categories',
    label: 'Categorias',
    shortLabel: 'Categorias',
    group: 'finance',
    icon: Shapes,
    status: 'live',
    description: 'Categorias padrao e personalizadas protegidas por user_id.',
    legacyRoute: 'categories',
  },
  transactions: {
    key: 'transactions',
    path: '/transactions',
    label: 'Transacoes',
    shortLabel: 'Receitas/Despesas',
    group: 'finance',
    icon: ArrowLeftRight,
    status: 'live',
    description: 'Receitas e despesas consolidadas em uma experiencia unica.',
    legacyRoute: 'transactions',
  },
  withdrawals: {
    key: 'withdrawals',
    path: '/withdrawals',
    label: 'Retiradas',
    shortLabel: 'Retiradas',
    group: 'finance',
    icon: CircleDollarSign,
    status: 'bridge',
    description: 'Lancamentos de retirada seguem disponiveis no modulo PHP atual.',
    legacyRoute: 'withdrawals',
  },
  debts: {
    key: 'debts',
    path: '/debts',
    label: 'Dividas',
    shortLabel: 'Dividas',
    group: 'finance',
    icon: Landmark,
    status: 'bridge',
    description: 'Gestao de dividas e parcelas ainda roda no fluxo legado.',
    legacyRoute: 'debts',
  },
  budgets: {
    key: 'budgets',
    path: '/budgets',
    label: 'Orcamentos',
    shortLabel: 'Orcamentos',
    group: 'planning',
    icon: PiggyBank,
    status: 'bridge',
    description: 'Limites mensais por categoria continuam ativos no backend PHP.',
    legacyRoute: 'budgets',
  },
  goals: {
    key: 'goals',
    path: '/goals',
    label: 'Metas',
    shortLabel: 'Metas',
    group: 'planning',
    icon: Goal,
    status: 'bridge',
    description: 'Metas financeiras seguem disponiveis no legado enquanto a UI React evolui.',
    legacyRoute: 'goals',
  },
  targets: {
    key: 'targets',
    path: '/targets',
    label: 'Alvos e Execucao',
    shortLabel: 'Alvos e Execucao',
    group: 'planning',
    icon: Rocket,
    status: 'live',
    description: 'Plano ativo, proximas acoes, score e inteligencia de execucao.',
    legacyRoute: 'targets',
  },
  agenda: {
    key: 'agenda',
    path: '/agenda',
    label: 'Agenda',
    shortLabel: 'Agenda',
    group: 'operations',
    icon: CalendarClock,
    status: 'live',
    description: 'Agenda priorizada a partir das regras atuais do backend.',
    legacyRoute: 'agenda_execution',
  },
  imports: {
    key: 'imports',
    path: '/imports',
    label: 'Importacao',
    shortLabel: 'Importacao',
    group: 'operations',
    icon: Upload,
    status: 'bridge',
    description: 'Upload e processamento de importacao continuam disponiveis no fluxo PHP.',
    legacyRoute: 'imports',
  },
  reports: {
    key: 'reports',
    path: '/reports',
    label: 'Relatorios',
    shortLabel: 'Relatorios',
    group: 'analysis',
    icon: Archive,
    status: 'bridge',
    description: 'Analises e visoes consolidadas seguem acessiveis no modulo legado.',
    legacyRoute: 'reports',
  },
  profile: {
    key: 'profile',
    path: '/profile',
    label: 'Meu acesso',
    shortLabel: 'Meu acesso',
    group: 'account',
    icon: UserRound,
    status: 'bridge',
    description: 'Dados do usuario e troca de senha continuam preservados no backend atual.',
    legacyRoute: 'profile',
  },
  users: {
    key: 'users',
    path: '/users',
    label: 'Usuarios',
    shortLabel: 'Usuarios',
    group: 'admin',
    icon: Users,
    status: 'bridge',
    description: 'Modulo administrativo existente no legado e mantido para contas admin.',
    legacyRoute: 'users',
    adminOnly: true,
  },
  logout: {
    key: 'logout',
    path: '/logout',
    label: 'Sair',
    shortLabel: 'Sair',
    group: 'account',
    icon: LogOut,
    status: 'action',
    description: 'Encerrar a sessao atual da release React.',
  },
};

export const menuGroups = [
  { key: 'overview', label: 'Visao geral' },
  { key: 'finance', label: 'Financeiro' },
  { key: 'planning', label: 'Planejamento' },
  { key: 'operations', label: 'Operacao' },
  { key: 'analysis', label: 'Analise' },
  { key: 'account', label: 'Conta' },
  { key: 'admin', label: 'Admin' },
];

export function getNavigationGroups({ isAdmin = false } = {}) {
  return menuGroups
    .map((group) => ({
      ...group,
      items: Object.values(moduleRegistry).filter((item) => {
        if (item.group !== group.key) {
          return false;
        }

        if (item.adminOnly && !isAdmin) {
          return false;
        }

        return true;
      }),
    }))
    .filter((group) => group.items.length > 0);
}

export function getBridgeModules({ isAdmin = false } = {}) {
  return Object.values(moduleRegistry).filter((item) => {
    if (item.status !== 'bridge') {
      return false;
    }

    if (item.adminOnly && !isAdmin) {
      return false;
    }

    return true;
  });
}

export function buildLegacyHref(basePath = '/', route = '') {
  const normalizedBase = String(basePath || '/').endsWith('/')
    ? String(basePath || '/')
    : `${String(basePath || '/')}/`;

  if (!route) {
    return normalizedBase;
  }

  return `${normalizedBase}index.php?route=${route}`;
}
