export const onboardingTourSteps = [
  {
    id: 'dashboard',
    title: 'Dashboard',
    description: 'Veja rapidamente como seu financeiro esta se comportando.',
    targetSelector: '[data-onboarding-id="dashboard-hero"]',
    placement: 'bottom',
  },
  {
    id: 'main-indicators',
    title: 'Indicadores principais',
    description: 'Acompanhe saldo, receitas e despesas em um so lugar.',
    targetSelector: '[data-onboarding-id="dashboard-indicators"]',
    placement: 'bottom',
  },
  {
    id: 'sidebar',
    title: 'Menu lateral',
    description: 'Acesse contas, transacoes e planejamento por aqui.',
    targetSelector: '[data-onboarding-id="main-navigation"]',
    placement: 'right',
  },
  {
    id: 'transactions',
    title: 'Transacoes',
    description: 'Registre entradas e saidas para refletir sua realidade.',
    targetSelector: '[data-onboarding-id="nav-transactions"]',
    placement: 'right',
  },
  {
    id: 'planning',
    title: 'Planejamento',
    description: 'Defina metas e acompanhe suas acoes.',
    targetSelector: '[data-onboarding-id="nav-targets"]',
    placement: 'right',
  },
];

export const onboardingChecklistConfig = [
  {
    key: 'first_account',
    label: 'Criar sua primeira conta',
    path: '/accounts',
    moduleLabel: 'Contas',
    isComplete: (stats) => Number(stats.accounts_count || 0) > 0,
  },
  {
    key: 'first_transaction',
    label: 'Registrar uma transacao',
    path: '/transactions',
    moduleLabel: 'Transacoes',
    isComplete: (stats) => Number(stats.transactions_count || 0) > 0,
  },
  {
    key: 'first_target',
    label: 'Criar um alvo',
    path: '/targets',
    moduleLabel: 'Planejamento',
    isComplete: (stats) => Number(stats.targets_count || 0) > 0,
  },
  {
    key: 'first_action',
    label: 'Criar uma acao',
    path: '/targets',
    moduleLabel: 'Planejamento',
    isComplete: (stats) => Number(stats.actions_count || 0) > 0,
  },
  {
    key: 'first_completed_action',
    label: 'Concluir uma acao',
    path: '/agenda',
    moduleLabel: 'Agenda',
    isComplete: (stats) => Number(stats.completed_actions_count || 0) > 0,
  },
];
