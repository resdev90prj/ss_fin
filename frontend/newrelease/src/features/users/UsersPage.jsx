import { useEffect, useMemo, useState } from 'react';
import { KeyRound, ShieldCheck, UserPlus, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber } from '../../lib/formatters';

const emptyCreateForm = {
  name: '',
  email: '',
  password: '',
  confirm_password: '',
  role: 'user',
  status: '1',
};

const emptyPasswordForm = {
  new_password: '',
  confirm_password: '',
};

export default function UsersPage() {
  const { session, refreshSession } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [createForm, setCreateForm] = useState(emptyCreateForm);
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [editForm, setEditForm] = useState(null);
  const [passwordForm, setPasswordForm] = useState(emptyPasswordForm);
  const [saving, setSaving] = useState('');

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

  const users = data?.items || [];
  const selectedUser = useMemo(
    () => users.find((user) => user.id === selectedUserId) || null,
    [users, selectedUserId],
  );

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

  function selectUser(user) {
    setSelectedUserId(user.id);
    setEditForm({
      id: user.id,
      name: user.name || '',
      email: user.email || '',
      role: user.role || 'user',
      status: String(user.status ?? 1),
    });
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
          csrf_token: session.csrf_token,
        },
      });
      setCreateForm(emptyCreateForm);
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
        data: {
          ...editForm,
          csrf_token: session.csrf_token,
        },
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

  if (!session.scope?.is_admin) {
    return (
      <section className="state-card">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-slate-950">Acesso restrito</h2>
          <p className="mt-1 text-sm text-slate-600">
            Este modulo administrativo esta disponivel apenas para contas com perfil admin.
          </p>
        </div>
      </section>
    );
  }

  if (loading && !data) {
    return <LoadingState text="Carregando a gestao administrativa de usuarios e escopos." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Usuarios indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar os usuarios.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const scope = session.scope || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Admin</span>
          <h1>Gerencie acessos, perfis, status e escopo sem sair do mesmo workspace.</h1>
          <p>
            O modulo administrativo agora esta funcional na mesma interface, mantendo a mesma
            seguranca, a mesma sessao PHP e o mesmo isolamento por usuario do sistema atual.
          </p>
        </div>
      </section>

      {scope.scoped_user_id ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>Escopo ativo no usuario ID {scope.scoped_user_id}.</span>
            <Button type="button" variant="outline" onClick={handleClearScope}>
              Limpar escopo
            </Button>
          </div>
        </div>
      ) : null}

      <div className="stats-grid">
        <StatCard label="Usuarios" value={formatNumber(summary.total)} icon={<ShieldCheck className="h-5 w-5" />} />
        <StatCard label="Ativos" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Admins" value={formatNumber(summary.admin_count)} tone="accent" />
        <StatCard label="Inativos" value={formatNumber(summary.inactive_count)} tone="danger" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title="Novo acesso" subtitle="Ao criar um usuario, as categorias padrao do sistema continuam sendo provisionadas no backend.">
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-7" onSubmit={submitCreate}>
          <label className="xl:col-span-2">
            <span>Nome</span>
            <input name="name" value={createForm.name} onChange={handleCreateChange} required />
          </label>
          <label className="xl:col-span-2">
            <span>E-mail</span>
            <input name="email" type="email" value={createForm.email} onChange={handleCreateChange} required />
          </label>
          <label>
            <span>Senha</span>
            <input name="password" type="password" value={createForm.password} onChange={handleCreateChange} minLength="6" required />
          </label>
          <label>
            <span>Confirmar senha</span>
            <input name="confirm_password" type="password" value={createForm.confirm_password} onChange={handleCreateChange} minLength="6" required />
          </label>
          <label>
            <span>Perfil</span>
            <select name="role" value={createForm.role} onChange={handleCreateChange}>
              <option value="user">user</option>
              <option value="admin">admin</option>
            </select>
          </label>
          <label>
            <span>Status</span>
            <select name="status" value={createForm.status} onChange={handleCreateChange}>
              <option value="1">Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </label>
          <div className="flex items-end">
            <Button type="submit" disabled={saving === 'create'}>
              <UserPlus className="h-4 w-4" />
              {saving === 'create' ? 'Criando...' : 'Criar usuario'}
            </Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Acessos existentes" subtitle="Selecione um usuario para editar dados ou redefinir a senha sem sair desta tela.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.id}>
                  <td>{user.id}</td>
                  <td>
                    <strong>{user.name}</strong>
                    <small>
                      {user.is_self ? 'voce' : 'usuario'}{user.is_scoped ? ' | escopo ativo' : ''}
                    </small>
                  </td>
                  <td>{user.email}</td>
                  <td>{user.role}</td>
                  <td>{Number(user.status) === 1 ? 'ativo' : 'inativo'}</td>
                  <td>{user.created_at}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => selectUser(user)}>
                        Editar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleScope(user.id)}>
                        Ver dados
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
                  <td colSpan="7" className="empty-cell">Nenhum usuario cadastrado.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>

      {selectedUser ? (
        <div className="grid gap-6 xl:grid-cols-2">
          <SectionCard
            title={`Editar ${selectedUser.name}`}
            subtitle="Altere nome, e-mail, perfil e status do usuario selecionado."
            action={
              <Button type="button" variant="outline" onClick={() => {
                setSelectedUserId(null);
                setEditForm(null);
                setPasswordForm(emptyPasswordForm);
              }}
              >
                <X className="h-4 w-4" />
                Fechar
              </Button>
            }
          >
            {editForm ? (
              <form className="grid gap-4 md:grid-cols-2" onSubmit={submitEdit}>
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
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                  </select>
                </label>
                <label>
                  <span>Status</span>
                  <select name="status" value={editForm.status} onChange={handleEditChange}>
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                  </select>
                </label>
                <div className="md:col-span-2">
                  <Button type="submit" disabled={saving === 'edit'}>
                    {saving === 'edit' ? 'Salvando...' : 'Salvar alteracoes'}
                  </Button>
                </div>
              </form>
            ) : null}
          </SectionCard>

          <SectionCard title="Redefinir senha" subtitle="A nova senha do usuario selecionado continua sendo armazenada via hash no backend.">
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
