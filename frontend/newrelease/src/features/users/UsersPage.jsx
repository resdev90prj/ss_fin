import { useEffect, useMemo, useState } from 'react';
import { KeyRound, ShieldCheck, UserPlus, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber } from '../../lib/formatters';
import UserModuleSelector from './UserModuleSelector';
import {
  createModuleSelection,
  defaultEnabledModulesForRole,
  extractEnabledModules,
  summarizeEnabledModules,
} from './moduleAccess';

const emptyCreateForm = {
  name: '',
  email: '',
  password: '',
  confirm_password: '',
  role: 'user',
  status: '1',
  manager_user_id: '',
};

const emptyPasswordForm = {
  new_password: '',
  confirm_password: '',
};

function buildEditForm(user) {
  return {
    id: user.id,
    name: user.name || '',
    email: user.email || '',
    role: user.role || 'user',
    status: String(user.status ?? 1),
    manager_user_id: user.manager_user_id ? String(user.manager_user_id) : '',
  };
}

function buildUserPayload(form, session, moduleState) {
  const role = form.role || 'user';
  const payload = {
    ...form,
    manager_user_id: role === 'user' && form.manager_user_id ? Number(form.manager_user_id) : null,
    csrf_token: session.csrf_token,
  };

  if (role === 'user') {
    payload.enabled_modules = extractEnabledModules(moduleState);
  }

  return payload;
}

export default function UsersPage() {
  const { session, refreshSession } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [createForm, setCreateForm] = useState(emptyCreateForm);
  const [createModuleState, setCreateModuleState] = useState({});
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [editForm, setEditForm] = useState(null);
  const [editModuleState, setEditModuleState] = useState({});
  const [passwordForm, setPasswordForm] = useState(emptyPasswordForm);
  const [saving, setSaving] = useState('');

  const users = data?.items || [];
  const summary = data?.summary || {};
  const lookups = data?.lookups || {};
  const moduleOptions = lookups.modules || [];
  const roleOptions = lookups.roles || [];
  const managerOptions = lookups.managers || [];
  const selectedUser = useMemo(
    () => users.find((user) => user.id === selectedUserId) || null,
    [users, selectedUserId],
  );

  async function loadUsers(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/users', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar os usuarios.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadUsers(controller.signal);
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (!moduleOptions.length) {
      return;
    }

    if (Object.keys(createModuleState).length === 0) {
      const defaultModules = defaultEnabledModulesForRole(
        createForm.role,
        createForm.role === 'user' && Number(createForm.manager_user_id) > 0,
        moduleOptions,
      );
      setCreateModuleState(createModuleSelection(moduleOptions, defaultModules));
    }
  }, [moduleOptions, createForm.role, createForm.manager_user_id, createModuleState]);

  useEffect(() => {
    if (!selectedUser || !moduleOptions.length) {
      return;
    }

    setEditForm(buildEditForm(selectedUser));
    setEditModuleState(createModuleSelection(moduleOptions, selectedUser.enabled_modules || []));
  }, [selectedUserId, moduleOptions, selectedUser]);

  function resetCreateForm(nextRole = 'user', nextManagerUserId = '') {
    setCreateForm({
      ...emptyCreateForm,
      role: nextRole,
      manager_user_id: nextManagerUserId,
    });

    if (moduleOptions.length) {
      const defaultModules = defaultEnabledModulesForRole(
        nextRole,
        nextRole === 'user' && Number(nextManagerUserId) > 0,
        moduleOptions,
      );
      setCreateModuleState(createModuleSelection(moduleOptions, defaultModules));
    } else {
      setCreateModuleState({});
    }
  }

  function handleCreateChange(event) {
    const { name, value } = event.target;
    const nextForm = {
      ...createForm,
      [name]: value,
    };

    if (name === 'role' && value !== 'user') {
      nextForm.manager_user_id = '';
    }

    setCreateForm(nextForm);

    if (name === 'role' || name === 'manager_user_id') {
      const defaultModules = defaultEnabledModulesForRole(
        nextForm.role,
        nextForm.role === 'user' && Number(nextForm.manager_user_id) > 0,
        moduleOptions,
      );
      setCreateModuleState(createModuleSelection(moduleOptions, defaultModules));
    }
  }

  function handleEditChange(event) {
    const { name, value } = event.target;
    setEditForm((current) => {
      if (!current) {
        return current;
      }

      const nextForm = {
        ...current,
        [name]: value,
      };

      if (name === 'role' && value !== 'user') {
        nextForm.manager_user_id = '';
      }

      if (name === 'role' || name === 'manager_user_id') {
        const defaultModules = defaultEnabledModulesForRole(
          nextForm.role,
          nextForm.role === 'user' && Number(nextForm.manager_user_id) > 0,
          moduleOptions,
        );
        setEditModuleState(createModuleSelection(moduleOptions, defaultModules));
      }

      return nextForm;
    });
  }

  function handlePasswordChange(event) {
    const { name, value } = event.target;
    setPasswordForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function toggleCreateModule(moduleKey) {
    setCreateModuleState((current) => ({
      ...current,
      [moduleKey]: !current[moduleKey],
    }));
  }

  function toggleEditModule(moduleKey) {
    setEditModuleState((current) => ({
      ...current,
      [moduleKey]: !current[moduleKey],
    }));
  }

  function selectUser(user) {
    setSelectedUserId(user.id);
    setEditForm(buildEditForm(user));
    setEditModuleState(createModuleSelection(moduleOptions, user.enabled_modules || []));
    setPasswordForm(emptyPasswordForm);
    setError('');
    setNotice('');
  }

  async function submitCreate(event) {
    event.preventDefault();
    setSaving('create');
    setError('');
    setNotice('');

    try {
      await apiRequest('/users', {
        method: 'POST',
        data: buildUserPayload(createForm, session, createModuleState),
      });
      resetCreateForm();
      setNotice('Usuario criado com sucesso.');
      await loadUsers();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel criar o usuario.');
    } finally {
      setSaving('');
    }
  }

  async function submitEdit(event) {
    event.preventDefault();
    if (!editForm) {
      return;
    }

    setSaving('edit');
    setError('');
    setNotice('');

    try {
      await apiRequest('/users/update', {
        method: 'POST',
        data: buildUserPayload(editForm, session, editModuleState),
      });
      await refreshSession();
      setNotice('Usuario atualizado com sucesso.');
      await loadUsers();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel atualizar o usuario.');
    } finally {
      setSaving('');
    }
  }

  async function submitPassword(event) {
    event.preventDefault();
    if (!selectedUser) {
      return;
    }

    setSaving('password');
    setError('');
    setNotice('');

    try {
      await apiRequest('/users/reset-password', {
        method: 'POST',
        data: {
          id: selectedUser.id,
          ...passwordForm,
          csrf_token: session.csrf_token,
        },
      });
      setPasswordForm(emptyPasswordForm);
      setNotice('Senha redefinida com sucesso.');
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel redefinir a senha.');
    } finally {
      setSaving('');
    }
  }

  async function handleToggleStatus(userId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/users/toggle-status', {
        method: 'POST',
        data: {
          id: userId,
          csrf_token: session.csrf_token,
        },
      });
      await refreshSession();
      setNotice('Status do usuario atualizado com sucesso.');
      await loadUsers();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel alterar o status do usuario.');
    }
  }

  async function handleScope(userId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/users/scope', {
        method: 'POST',
        data: {
          user_id: userId,
          csrf_token: session.csrf_token,
        },
      });
      await refreshSession();
      setNotice('Escopo de visualizacao alterado com sucesso.');
      await loadUsers();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel alterar o escopo.');
    }
  }

  async function handleClearScope() {
    setError('');
    setNotice('');

    try {
      await apiRequest('/users/clear-scope', {
        method: 'POST',
        data: {
          csrf_token: session.csrf_token,
        },
      });
      await refreshSession();
      setNotice('Escopo de visualizacao limpo com sucesso.');
      await loadUsers();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel limpar o escopo.');
    }
  }

  function closeEditor() {
    setSelectedUserId(null);
    setEditForm(null);
    setEditModuleState({});
    setPasswordForm(emptyPasswordForm);
  }

  if (!session.permissions?.capabilities?.can_access_user_admin) {
    return (
      <section className="state-card">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-slate-950">Acesso restrito</h2>
          <p className="mt-1 text-sm text-slate-600">
            Este modulo esta disponivel apenas para perfis administradores.
          </p>
        </div>
      </section>
    );
  }

  if (loading && !data) {
    return <LoadingState text="Carregando usuarios, perfis, vinculos e modulos liberados." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Usuarios indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar os usuarios.'}</p>
      </section>
    );
  }

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Admin</span>
          <h1>Gerencie perfis, gestores, clientes e modulos da newrelease em um so lugar.</h1>
          <p>
            Crie acessos, vincule clientes a gestores financeiros e defina quais modulos cada usuario pode utilizar.
          </p>
        </div>
      </section>

      {session.scope?.is_scoped ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>Visualizacao ativa em {session.scope.current_user_name || `ID ${session.scope.scoped_user_id}`}.</span>
            <Button type="button" variant="outline" onClick={handleClearScope}>
              Limpar escopo
            </Button>
          </div>
        </div>
      ) : null}

      <div className="stats-grid">
        <StatCard label="Usuarios" value={formatNumber(summary.total)} icon={<ShieldCheck className="h-5 w-5" />} />
        <StatCard label="Gestores" value={formatNumber(summary.manager_count)} tone="accent" />
        <StatCard label="Clientes vinculados" value={formatNumber(summary.managed_clients_count)} tone="positive" />
        <StatCard label="Somente dashboard" value={formatNumber(summary.dashboard_only_count)} tone="warning" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title="Novo acesso" subtitle="Crie administradores, gestores financeiros e clientes com os modulos adequados.">
        <form className="grid gap-4 xl:grid-cols-6" onSubmit={submitCreate}>
          <label className="xl:col-span-2">
            <span>Nome</span>
            <input name="name" value={createForm.name} onChange={handleCreateChange} required />
          </label>
          <label className="xl:col-span-2">
            <span>E-mail</span>
            <input name="email" type="email" value={createForm.email} onChange={handleCreateChange} required />
          </label>
          <label>
            <span>Perfil</span>
            <select name="role" value={createForm.role} onChange={handleCreateChange}>
              {roleOptions.map((roleOption) => (
                <option key={roleOption.value} value={roleOption.value}>
                  {roleOption.label}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span>Status</span>
            <select name="status" value={createForm.status} onChange={handleCreateChange}>
              <option value="1">Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </label>

          <label>
            <span>Senha</span>
            <input name="password" type="password" value={createForm.password} onChange={handleCreateChange} minLength="6" required />
          </label>
          <label>
            <span>Confirmar senha</span>
            <input name="confirm_password" type="password" value={createForm.confirm_password} onChange={handleCreateChange} minLength="6" required />
          </label>

          {createForm.role === 'user' ? (
            <label className="xl:col-span-2">
              <span>Gestor responsavel</span>
              <select name="manager_user_id" value={createForm.manager_user_id} onChange={handleCreateChange}>
                <option value="">Sem gestor vinculado</option>
                {managerOptions.map((manager) => (
                  <option key={manager.id} value={manager.id}>
                    {manager.name}
                  </option>
                ))}
              </select>
            </label>
          ) : (
            <div className="xl:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
              Este perfil recebe acesso amplo de acordo com o papel selecionado.
            </div>
          )}

          <div className="xl:col-span-6">
            <UserModuleSelector
              moduleOptions={moduleOptions}
              selection={createModuleState}
              onToggle={toggleCreateModule}
              disabled={createForm.role !== 'user'}
            />
          </div>

          <div className="xl:col-span-6 flex justify-end">
            <Button type="submit" disabled={saving === 'create'}>
              <UserPlus className="h-4 w-4" />
              {saving === 'create' ? 'Criando...' : 'Criar usuario'}
            </Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Acessos existentes" subtitle="Selecione um usuario para editar perfil, gestor vinculado, modulos e senha.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Perfil</th>
                <th>Gestor</th>
                <th>Modulos</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.id}>
                  <td>
                    <strong>{user.name}</strong>
                    <small>{user.email}</small>
                  </td>
                  <td>{user.role_label}</td>
                  <td>{user.manager_name || '-'}</td>
                  <td>{summarizeEnabledModules(user.enabled_modules, moduleOptions)}</td>
                  <td>{Number(user.status) === 1 ? 'ativo' : 'inativo'}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => selectUser(user)}>
                        Editar
                      </Button>
                      {user.can_scope ? (
                        <Button type="button" size="sm" variant="ghost" onClick={() => handleScope(user.id)}>
                          Ver dados
                        </Button>
                      ) : null}
                      {user.can_manage ? (
                        <Button type="button" size="sm" variant="ghost" onClick={() => handleToggleStatus(user.id)}>
                          {Number(user.status) === 1 ? 'Desativar' : 'Ativar'}
                        </Button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}

              {users.length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Nenhum usuario cadastrado.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>

      {selectedUser && editForm ? (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(360px,0.7fr)]">
          <SectionCard
            title={`Editar ${selectedUser.name}`}
            subtitle="Atualize dados, vinculo de gestor e modulos habilitados do usuario selecionado."
            action={(
              <Button type="button" variant="outline" onClick={closeEditor}>
                <X className="h-4 w-4" />
                Fechar
              </Button>
            )}
          >
            <form className="space-y-5" onSubmit={submitEdit}>
              <div className="grid gap-4 md:grid-cols-2">
                <label className="md:col-span-2">
                  <span>Nome</span>
                  <input name="name" value={editForm.name} onChange={handleEditChange} required />
                </label>
                <label className="md:col-span-2">
                  <span>E-mail</span>
                  <input name="email" type="email" value={editForm.email} onChange={handleEditChange} required />
                </label>
                <label>
                  <span>Perfil</span>
                  <select name="role" value={editForm.role} onChange={handleEditChange}>
                    {roleOptions.map((roleOption) => (
                      <option key={roleOption.value} value={roleOption.value}>
                        {roleOption.label}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>Status</span>
                  <select name="status" value={editForm.status} onChange={handleEditChange}>
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                  </select>
                </label>

                {editForm.role === 'user' ? (
                  <label className="md:col-span-2">
                    <span>Gestor responsavel</span>
                    <select name="manager_user_id" value={editForm.manager_user_id} onChange={handleEditChange}>
                      <option value="">Sem gestor vinculado</option>
                      {managerOptions.map((manager) => (
                        <option key={manager.id} value={manager.id}>
                          {manager.name}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : (
                  <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Este perfil utiliza acesso amplo de acordo com o papel selecionado.
                  </div>
                )}
              </div>

              <UserModuleSelector
                moduleOptions={moduleOptions}
                selection={editModuleState}
                onToggle={toggleEditModule}
                disabled={editForm.role !== 'user'}
              />

              <div>
                <Button type="submit" disabled={saving === 'edit'}>
                  {saving === 'edit' ? 'Salvando...' : 'Salvar alteracoes'}
                </Button>
              </div>
            </form>
          </SectionCard>

          <SectionCard title="Redefinir senha" subtitle="Defina uma nova senha para o usuario selecionado sem sair desta tela.">
            <form className="space-y-4" onSubmit={submitPassword}>
              <label>
                <span>Nova senha</span>
                <input name="new_password" type="password" value={passwordForm.new_password} onChange={handlePasswordChange} minLength="6" required />
              </label>
              <label>
                <span>Confirmar nova senha</span>
                <input name="confirm_password" type="password" value={passwordForm.confirm_password} onChange={handlePasswordChange} minLength="6" required />
              </label>
              <Button type="submit" disabled={saving === 'password'}>
                <KeyRound className="h-4 w-4" />
                {saving === 'password' ? 'Salvando...' : 'Salvar nova senha'}
              </Button>
            </form>
          </SectionCard>
        </div>
      ) : null}
    </div>
  );
}
