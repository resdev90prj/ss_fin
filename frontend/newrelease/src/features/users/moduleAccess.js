export function createModuleSelection(moduleOptions = [], enabledModules = []) {
  const enabledSet = new Set(enabledModules);

  return moduleOptions.reduce((accumulator, moduleOption) => ({
    ...accumulator,
    [moduleOption.key]: enabledSet.has(moduleOption.key),
  }), {});
}

export function extractEnabledModules(selection = {}) {
  return Object.entries(selection)
    .filter(([, enabled]) => Boolean(enabled))
    .map(([moduleKey]) => moduleKey);
}

export function groupModuleOptions(moduleOptions = []) {
  return moduleOptions.reduce((groups, moduleOption) => {
    const groupKey = moduleOption.group || 'other';
    const groupLabel = moduleOption.group_label || 'Outros';

    if (!groups[groupKey]) {
      groups[groupKey] = {
        key: groupKey,
        label: groupLabel,
        items: [],
      };
    }

    groups[groupKey].items.push(moduleOption);
    return groups;
  }, {});
}

export function summarizeEnabledModules(enabledModules = [], moduleOptions = []) {
  if (!enabledModules.length) {
    return 'Somente dashboard';
  }

  const labelsByKey = Object.fromEntries(moduleOptions.map((moduleOption) => [moduleOption.key, moduleOption.label]));
  return enabledModules
    .map((moduleKey) => labelsByKey[moduleKey] || moduleKey)
    .join(', ');
}

export function defaultEnabledModulesForRole(role, isManagedClient, moduleOptions = []) {
  if (role === 'admin' || role === 'gestor_financeiro') {
    return moduleOptions.map((moduleOption) => moduleOption.key);
  }

  if (isManagedClient) {
    return [];
  }

  return moduleOptions.map((moduleOption) => moduleOption.key);
}
