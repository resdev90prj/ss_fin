import { useEffect, useState } from 'react';
import { Pencil, Vault, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatNumber } from '../../lib/formatters';

const emptyForm = {
  name: '',
  objective: '',
  account_id: '',
  balance: '0',
  status: 'active',
};

export default function BoxesPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function loadBoxes(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/boxes', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar os caixas.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadBoxes(controller.signal);
    return () => controller.abort();
  }, []);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleEdit(box) {
    setEditingId(box.id);
    setForm({
      name: box.name || '',
      objective: box.objective || '',
      account_id: box.account_id ? String(box.account_id) : '',
      balance: String(box.balance ?? 0),
      status: box.status || 'active',
    });
    setError('');
    setNotice('');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      await apiRequest(editingId ? '/boxes/update' : '/boxes', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Caixa atualizado com sucesso.' : 'Caixa criado com sucesso.');
      resetForm();
      await loadBoxes();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar o caixa.');
    } finally {
      setSubmitting(false);
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando os caixas e seus vinculos operacionais." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Caixas indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar os caixas.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const accounts = data.lookups?.accounts || [];

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Caixas virtuais</span>
          <h1>Separe reservas, frentes e objetivos em uma operacao unica.</h1>
          <p>
            Organize saldos dedicados por objetivo e mantenha vinculos com contas quando fizer sentido.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Total de caixas" value={formatNumber(summary.total)} icon={<Vault className="h-5 w-5" />} />
        <StatCard label="Ativos" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Vinculados a contas" value={formatNumber(summary.linked_account_count)} tone="accent" />
        <StatCard label="Saldo acumulado" value={formatCurrency(summary.total_balance)} tone="neutral" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard
        title={editingId ? 'Editar caixa' : 'Novo caixa'}
        subtitle="Defina nome, objetivo, saldo e vinculo opcional com uma conta."
      >
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-5" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Nome do caixa</span>
            <input name="name" value={form.name} onChange={handleChange} placeholder="Reserva tributaria" required />
          </label>

          <label className="xl:col-span-2">
            <span>Objetivo</span>
            <input name="objective" value={form.objective} onChange={handleChange} placeholder="Para que este caixa existe?" />
          </label>

          <label>
            <span>Conta vinculada</span>
            <select name="account_id" value={form.account_id} onChange={handleChange}>
              <option value="">Sem conta</option>
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Saldo</span>
            <input name="balance" type="number" step="0.01" value={form.balance} onChange={handleChange} />
          </label>

          <label>
            <span>Status</span>
            <select name="status" value={form.status} onChange={handleChange}>
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
            </select>
          </label>

          <div className="flex items-end gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Criar caixa'}
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

      <SectionCard title="Caixas cadastrados" subtitle="Visao consolidada dos saldos e vinculos operacionais.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Caixa</th>
                <th>Conta</th>
                <th>Objetivo</th>
                <th>Saldo</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((box) => (
                <tr key={box.id}>
                  <td>
                    <strong>{box.name}</strong>
                    <small>ID {box.id}</small>
                  </td>
                  <td>{box.account_name || 'Sem conta'}</td>
                  <td>{box.objective || '-'}</td>
                  <td>{formatCurrency(box.balance)}</td>
                  <td>{box.is_active ? 'Ativo' : 'Inativo'}</td>
                  <td>
                    <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(box)}>
                      <Pencil className="h-4 w-4" />
                      Editar
                    </Button>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Nenhum caixa cadastrado para este usuario.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
