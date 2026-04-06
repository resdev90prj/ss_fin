import { useEffect, useMemo, useState } from 'react';
import { KeyRound, UserPlus, X } from 'lucide-react';
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
  extractEnabledModules,
  summarizeEnabledModules,
} from './moduleAccess';

const emptyCreateForm = {
  name: '',
  email: '',
  password: '',
  confirm_password: '',
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
    status: String(user.status ?? 1),
    manager_user_id: user.manager_user_id ? String(user.manager_user_id) : '',
  };
}

export default function ManagerClientsPage() {
  const { session, refreshSession } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [managerFilter, setManagerFilter] = useState('');
  const [createForm, setCreateForm] = useState(emptyCreateForm);
  const [createModuleState, setCreateModuleState] = useState({});
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [editForm, setEditForm] = useState(null);
  const [editModuleState, setEditModuleState] = useState({});
  const [passwordForm, setPasswordForm] = useState(emptyPasswordForm);
  const [saving, setSaving] = useState('');

  const users = data?.items || [];
  const summary = data?.summary || {};
  const moduleOptions = data?.lookups?.modules || [];
  const managerOptions = data?.lookups?.managers || [];
  const selectedUser = useMemo(
    () => users.find((user) => user.id === selectedUserId) || null,
    [users, selectedUserId],
  );

  const isAdmin = session.permissions?.capabilities?.is_admin;
  const isManager = session.permissions?.capabilities?.is_financial_manager;

  async function loadClients(signal, nextManagerFilter = managerFilter) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/users', {
        data: {
          relationship: 'managed',
          manager_user_id: nextManagerFilter ? Number(nextManagerFilter) : undefined,
        },
        signal,
      });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar os clientes.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadClients(controller.signal);
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (!moduleOptions.length) {
      return;
    }

    if (Object.keys(createModuleState).length === 0) {
      setCreateModuleState(createModuleSelection(moduleOptions, []));
    }
  }, [moduleOptions, createModuleState]);

  useEffect(() => {
    if (!selectedUser || !moduleOptions.length) {
      return;
    }

    setEditForm(buildEditForm(selectedUser));
    setEditModuleState(createModuleSelection(moduleOptions, selectedUser.enabled_modules || []));
  }, [selectedUserId, selectedUser, moduleOptions]);

  useEffect(() => {
    if (isManager && session.user?.id) {
      setCreateForm((current) => ({
        ...current,
        manager_user_id: String(session.user.id),
      }));
    }
  }, [isManager, session.user]);

  function resetCreateForm() {
    setCreateForm({
      ...emptyCreateForm,
      manager_user_id: isManager && session.user?.id ? String(session.user.id) : '',
    });
    setCreateModuleState(createModuleSelection(moduleOptions, []));
  }

  function handleCreateChange(event) {
    const { name, value } = event.target;
    setCreateForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleEditChange(event) {
    const { name, value } = event.target;
    setEditForm((current) => ({
      ...current,
      [name]: value,
    }));
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
        data: {
          ...createForm,
          role: 'user',
          manager_user_id: createForm.manager_user_id ? Number(createForm.manager_user_id) : null,
          enabled_modules: extractEnabledModules(createModuleState),
          csrf_token: session.csrf_token,
        },
      });
      resetCreateForm();
      setNotice('Cliente criado com sucesso.');
      await loadClients();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel criar o cliente.');
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
        data: {
          ...editForm,
          role: 'user',
          manager_user_id: editForm.manager_user_id ? Number(editForm.manager_user_id) : null,
          enabled_modules: extractEnabledModules(editModuleState),
          csrf_token: session.csrf_token,
        },
      });
      await refreshSession();
      setNotice('Cliente atualizado com sucesso.');
      await loadClients();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel atualizar o cliente.');
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
      setNotice('Status do cliente atualizado com sucesso.');
      await loadClients();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel alterar o status do cliente.');
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
      setNotice('Escopo do cliente ativado com sucesso.');
      await loadClients();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel ativar o escopo deste cliente.');
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
      setNotice('Escopo do cliente limpo com sucesso.');
      await loadClients();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel limpar o escopo atual.');
    }
  }

  async function applyManagerFilter(event) {
    event.preventDefault();
    await loadClients(undefined, managerFilter);
  }

  function closeEditor() {
    setSelectedUserId(null);
    setEditForm(null);
    setEditModuleState({});
    setPasswordForm(emptyPasswordForm);
  }

  if (!session.permissions?.capabilities?.can_access_manager_area) {
    return (
      <section className="state-card">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-slate-950">Acesso restrito</h2>
          <p className="mt-1 text-sm text-slate-600">
            Esta area esta disponivel apenas para administradores e gestores financeiros.
          </p>
        </div>
      </section>
    );
  }

  if (loading && !data) {
    return <LoadingState text="Carregando clientes sob gestao e modulos habilitados." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Clientes indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar os clientes.'}</p>
      </section>
    );
  }

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Gestor Financeiro</span>
          <h1>Administre seus clientes, modulos liberados e contexto operacional sem sair da newrelease.</h1>
          <p>
            Crie clientes vinculados, ajuste acessos por modulo e entre no contexto de cada operacao quando precisar.
          </p>
        </div>
      </section>

      {session.scope?.is_scoped ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>Voce esta operando no contexto de {session.scope.current_user_name || `ID ${session.scope.scoped_user_id}`}.</span>
            <Button type="button" variant="outline" onClick={handleClearScope}>
              Voltar para meu contexto
            </Button>
          </div>
        </div>
      ) : null}

      <div className="stats-grid">
        <StatCard label="Clientes" value={formatNumber(summary.total)} />
        <StatCard label="Ativos" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Somente dashboard" value={formatNumber(summary.dashboard_only_count)} tone="warning" />
        <StatCard label="Planejamento ligado" value={formatNumber(summary.planning_enabled_count)} tone="accent" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      {isAdmin ? (
        <form className="filter-card" onSubmit={applyManagerFilter}>
          <label>
            <span>Filtrar por gestor</span>
            <select value={managerFilter} onChange={(event) => setManagerFilter(event.target.value)}>
              <option value="">Todos os gestores</option>
              {managerOptions.map((manager) => (
                <option key={manager.id} value={manager.id}>
                  {manager.name}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end">
            <Button type="submit">Aplicar filtro</Button>
          </div>
        </form>
      ) : null}

      <SectionCard title="Novo cliente" subtitle="Cadastre um cliente subordinado e escolha quais modulos ele podera usar.">
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
            <span>Status</span>
            <select name="status" value={createForm.status} onChange={handleCreateChange}>
              <option value="1">Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </label>
          <label>
            <span>Gestor responsavel</span>
            <select
              name="manager_user_id"
              value={createForm.manager_user_id}
              onChange={handleCreateChange}
              disabled={isManager}
              required={isAdmin}
            >
              {!isManager ? <option value="">Selecione um gestor</option> : null}
              {(isManager && session.user?.id) ? (
                <option value={session.user.id}>{session.user.name}</option>
              ) : null}
              {isAdmin ? managerOptions.map((manager) => (
                <option key={manager.id} value={manager.id}>
                  {manager.name}
                </option>
              )) : null}
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

          <div className="xl:col-span-6">
            <UserModuleSelector
              moduleOptions={moduleOptions}
              selection={createModuleState}
              onToggle={toggleCreateModule}
            />
          </div>

          <div className="xl:col-span-6 flex justify-end">
            <Button type="submit" disabled={saving === 'create'}>
              <UserPlus className="h-4 w-4" />
              {saving === 'create' ? 'Criando...' : 'Criar cliente'}
            </Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Carteira de clientes" subtitle="Veja os clientes do gestor selecionado e entre no contexto operacional de cada um.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Cliente</th>
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
                  <td>{user.manager_name || '-'}</td>
                  <td>{summarizeEnabledModules(user.enabled_modules, moduleOptions)}</td>
                  <td>{Number(user.status) === 1 ? 'ativo' : 'inativo'}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => selectUser(user)}>
                        Editar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleScope(user.id)}>
                        Operar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleToggleStatus(user.id)}>
                        {Number(user.status) === 1 ? 'Desativar' : 'Ativar'}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}

              {users.length === 0 ? (
                <tr>
                  <td colSpan="5" className="empty-cell">Nenhum cliente vinculado encontrado.</td>
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
            subtitle="Atualize dados basicos, gestor responsavel e modulos liberados para este cliente."
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
                  <span>Status</span>
                  <select name="status" value={editForm.status} onChange={handleEditChange}>
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                  </select>
                </label>
                <label>
                  <span>Gestor responsavel</span>
                  <select
                    name="manager_user_id"
                    value={editForm.manager_user_id}
                    onChange={handleEditChange}
                    disabled={isManager}
                  >
                    {!isManager ? <option value="">Selecione um gestor</option> : null}
                    {(isManager && session.user?.id) ? (
                      <option value={session.user.id}>{session.user.name}</option>
                    ) : null}
                    {isAdmin ? managerOptions.map((manager) => (
                      <option key={manager.id} value={manager.id}>
                        {manager.name}
                      </option>
                    )) : null}
                  </select>
                </label>
              </div>

              <UserModuleSelector
                moduleOptions={moduleOptions}
                selection={editModuleState}
                onToggle={toggleEditModule}
              />

              <div>
                <Button type="submit" disabled={saving === 'edit'}>
                  {saving === 'edit' ? 'Salvando...' : 'Salvar alteracoes'}
                </Button>
              </div>
            </form>
          </SectionCard>

          <SectionCard title="Redefinir senha" subtitle="Defina uma nova senha para o cliente selecionado.">
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
