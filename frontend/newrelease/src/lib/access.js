const roleLabels = {
  admin: 'Administrador',
  gestor_financeiro: 'Gestor financeiro',
  user: 'Cliente',
};

export function getNavigationModules(session) {
  return Array.isArray(session?.permissions?.navigation_modules)
    ? session.permissions.navigation_modules
    : ['dashboard', 'profile'];
}

export function getEffectiveModules(session) {
  return Array.isArray(session?.permissions?.effective_modules)
    ? session.permissions.effective_modules
    : ['dashboard', 'profile'];
}

export function hasNavigationAccess(session, moduleKey) {
  return getNavigationModules(session).includes(moduleKey);
}

export function hasEffectiveModule(session, moduleKey) {
  return getEffectiveModules(session).includes(moduleKey);
}

export function getRoleLabel(user) {
  if (!user) {
    return 'Usuario';
  }

  return user.role_label || roleLabels[user.role] || 'Usuario';
}
