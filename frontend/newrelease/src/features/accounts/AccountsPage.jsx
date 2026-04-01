import { useEffect, useState } from 'react';
import { Pencil, Power, WalletCards, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatNumber } from '../../lib/formatters';

const emptyForm = {
  name: '',
  type: 'PF',
  institution: '',
  initial_balance: '0',
  status: 'active',
};

export default function AccountsPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function loadAccounts(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/accounts', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as contas.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadAccounts(controller.signal);
    return () => controller.abort();
  }, []);

  function resetForm() {
    setForm(emptyForm);
    setEditingId(null);
  }

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleEdit(account) {
    setEditingId(account.id);
    setForm({
      name: account.name || '',
      type: account.type || 'PF',
      institution: account.institution || '',
      initial_balance: String(account.initial_balance ?? 0),
      status: account.status || 'active',
    });
    setNotice('');
    setError('');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setNotice('');
    setError('');

    try {
      await apiRequest(editingId ? '/accounts/update' : '/accounts', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Conta atualizada com sucesso.' : 'Conta criada com sucesso.');
      resetForm();
      await loadAccounts();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar a conta.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggle(accountId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/accounts/toggle', {
        method: 'POST',
        data: {
          id: accountId,
          csrf_token: session.csrf_token,
        },
      });

      setNotice('Status da conta atualizado com sucesso.');
      await loadAccounts();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel alterar o status da conta.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando as contas financeiras da sua operacao." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Contas indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar a lista de contas.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Contas financeiras</span>
          <h1>Cadastre e organize as contas que sustentam seu fluxo.</h1>
          <p>
            Mantenha suas contas organizadas, com status e saldo inicial sempre atualizados.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Total de contas" value={formatNumber(summary.total)} icon={<WalletCards className="h-5 w-5" />} />
        <StatCard label="Ativas" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Inativas" value={formatNumber(summary.inactive_count)} tone="danger" />
        <StatCard label="Saldo inicial total" value={formatCurrency(summary.total_initial_balance)} tone="accent" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard
        title={editingId ? 'Editar conta' : 'Nova conta'}
        subtitle="Cadastre contas e ajuste status, instituicao e saldo inicial."
      >
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-5" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Nome da conta</span>
            <input name="name" value={form.name} onChange={handleChange} placeholder="Conta principal" required />
          </label>

          <label>
            <span>Tipo</span>
            <select name="type" value={form.type} onChange={handleChange}>
              <option value="PF">PF</option>
              <option value="PJ">PJ</option>
            </select>
          </label>

          <label>
            <span>Instituicao</span>
            <input name="institution" value={form.institution} onChange={handleChange} placeholder="Banco ou carteira" />
          </label>

          <label>
            <span>Saldo inicial</span>
            <input name="initial_balance" type="number" step="0.01" value={form.initial_balance} onChange={handleChange} />
          </label>

          <label>
            <span>Status</span>
            <select name="status" value={form.status} onChange={handleChange}>
              <option value="active">Ativa</option>
              <option value="inactive">Inativa</option>
            </select>
          </label>

          <div className="flex items-end gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Criar conta'}
            </Button>
            {editingId ? (
              <Button type="button" variant="outline" onClick={resetForm}>
                <X className="h-4 w-4" />
                Cancelar
              </Button>
            ) : null}
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Cadastro atual" subtitle="Consulte as contas disponiveis e acompanhe o status de cada uma.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Conta</th>
                <th>Tipo</th>
                <th>Instituicao</th>
                <th>Saldo inicial</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((account) => (
                <tr key={account.id}>
                  <td>
                    <strong>{account.name}</strong>
                    <small>ID {account.id}</small>
                  </td>
                  <td>{account.type}</td>
                  <td>{account.institution || '-'}</td>
                  <td>{formatCurrency(account.initial_balance)}</td>
                  <td>{account.is_active ? 'Ativa' : 'Inativa'}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(account)}>
                        <Pencil className="h-4 w-4" />
                        Editar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleToggle(account.id)}>
                        <Power className="h-4 w-4" />
                        {account.is_active ? 'Inativar' : 'Ativar'}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Nenhuma conta encontrada para este usuario.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
