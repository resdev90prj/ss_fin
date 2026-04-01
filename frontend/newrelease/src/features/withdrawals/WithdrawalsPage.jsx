import { useEffect, useState } from 'react';
import { CircleDollarSign, Pencil, Trash2, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatDate, formatNumber } from '../../lib/formatters';

const withdrawalTypes = [
  'pro-labore',
  'distribuicao de lucro',
  'retirada do socio',
  'gasto pessoal pago pela empresa',
];

const initialFilters = {
  from: '',
  to: '',
};

const emptyForm = {
  description: '',
  amount: '',
  transaction_date: new Date().toISOString().slice(0, 10),
  mode: 'transicao',
  category_id: '',
  account_id: '',
  box_id: '',
  payment_method: withdrawalTypes[0],
  notes: '',
};

export default function WithdrawalsPage() {
  const { session } = useAuth();
  const [filters, setFilters] = useState(initialFilters);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function loadWithdrawals(nextFilters = filters, signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/transactions', {
        data: {
          ...nextFilters,
          type: 'partner_withdrawal',
        },
        signal,
      });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as retiradas.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadWithdrawals(filters, controller.signal);
    return () => controller.abort();
  }, []);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  function handleFilterChange(event) {
    const { name, value } = event.target;
    setFilters((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleFormChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  async function handleFilterSubmit(event) {
    event.preventDefault();
    await loadWithdrawals(filters);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      await apiRequest(editingId ? '/transactions/update' : '/transactions', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          type: 'partner_withdrawal',
          amount: Number(form.amount || 0),
          category_id: Number(form.category_id || 0),
          account_id: Number(form.account_id || 0),
          box_id: form.box_id ? Number(form.box_id) : null,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Retirada atualizada com sucesso.' : 'Retirada registrada com sucesso.');
      resetForm();
      await loadWithdrawals(filters);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar a retirada.');
    } finally {
      setSubmitting(false);
    }
  }

  function handleEdit(transaction) {
    setEditingId(transaction.id);
    setForm({
      description: transaction.description || '',
      amount: String(transaction.amount ?? ''),
      transaction_date: transaction.transaction_date || new Date().toISOString().slice(0, 10),
      mode: transaction.mode || 'transicao',
      category_id: transaction.category_id ? String(transaction.category_id) : '',
      account_id: transaction.account_id ? String(transaction.account_id) : '',
      box_id: transaction.box_id ? String(transaction.box_id) : '',
      payment_method: transaction.payment_method || withdrawalTypes[0],
      notes: transaction.notes || '',
    });
    setError('');
    setNotice('');
  }

  async function handleDelete(transactionId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/transactions/delete', {
        method: 'POST',
        data: {
          id: transactionId,
          csrf_token: session.csrf_token,
        },
      });

      if (editingId === transactionId) {
        resetForm();
      }

      setNotice('Retirada excluida com sucesso.');
      await loadWithdrawals(filters);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel excluir a retirada.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando o fluxo de retiradas do socio." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Retiradas indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as retiradas.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const lookups = data.lookups || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Retiradas</span>
          <h1>Registre pro-labore, distribuicao de lucro e retiradas operacionais sem sair do mesmo fluxo.</h1>
          <p>
            O fluxo de retiradas agora roda dentro da mesma experiencia usando o mesmo
            backend e as mesmas protecoes de ownership do sistema financeiro.
          </p>
        </div>
      </section>

      <form className="filter-card" onSubmit={handleFilterSubmit}>
        <label>
          <span>De</span>
          <input type="date" name="from" value={filters.from} onChange={handleFilterChange} />
        </label>

        <label>
          <span>Ate</span>
          <input type="date" name="to" value={filters.to} onChange={handleFilterChange} />
        </label>

        <div className="flex items-end">
          <Button type="submit">Aplicar filtros</Button>
        </div>
      </form>

      <div className="stats-grid">
        <StatCard label="Lancamentos" value={formatNumber(summary.total)} icon={<CircleDollarSign className="h-5 w-5" />} />
        <StatCard label="Valor total" value={formatCurrency(summary.withdrawal_total)} tone="warning" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title={editingId ? 'Editar retirada' : 'Nova retirada'} subtitle="Tipos suportados: pro-labore, distribuicao de lucro, retirada do socio e gasto pessoal pago pela empresa.">
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Descricao</span>
            <input name="description" value={form.description} onChange={handleFormChange} placeholder="Ex.: Pro-labore de abril" required />
          </label>

          <label>
            <span>Valor</span>
            <input name="amount" type="number" step="0.01" value={form.amount} onChange={handleFormChange} required />
          </label>

          <label>
            <span>Data</span>
            <input name="transaction_date" type="date" value={form.transaction_date} onChange={handleFormChange} required />
          </label>

          <label>
            <span>Tipo de retirada</span>
            <select name="payment_method" value={form.payment_method} onChange={handleFormChange}>
              {withdrawalTypes.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Modo</span>
            <select name="mode" value={form.mode} onChange={handleFormChange}>
              <option value="transitorio">Transitorio</option>
              <option value="transicao">Transicao</option>
              <option value="ideal">Ideal</option>
            </select>
          </label>

          <label>
            <span>Categoria</span>
            <select name="category_id" value={form.category_id} onChange={handleFormChange} required>
              <option value="">Selecione</option>
              {(lookups.categories || []).map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Conta</span>
            <select name="account_id" value={form.account_id} onChange={handleFormChange} required>
              <option value="">Selecione</option>
              {(lookups.accounts || []).map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Caixa</span>
            <select name="box_id" value={form.box_id} onChange={handleFormChange}>
              <option value="">Sem caixa</option>
              {(lookups.boxes || []).map((box) => (
                <option key={box.id} value={box.id}>
                  {box.name}
                </option>
              ))}
            </select>
          </label>

          <label className="xl:col-span-2">
            <span>Observacoes</span>
            <input name="notes" value={form.notes} onChange={handleFormChange} placeholder="Complementos opcionais" />
          </label>

          <div className="flex flex-wrap gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Registrar retirada'}
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

      <SectionCard title="Historico de retiradas" subtitle="Todas as retiradas registradas no periodo filtrado.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Descricao</th>
                <th>Valor</th>
                <th>Data</th>
                <th>Tipo de retirada</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((transaction) => (
                <tr key={transaction.id}>
                  <td>
                    <strong>{transaction.description}</strong>
                    <small>{transaction.notes || 'Sem observacoes'}</small>
                  </td>
                  <td>{formatCurrency(transaction.amount)}</td>
                  <td>{formatDate(transaction.transaction_date)}</td>
                  <td>{transaction.payment_method || '-'}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(transaction)}>
                        <Pencil className="h-4 w-4" />
                        Editar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleDelete(transaction.id)}>
                        <Trash2 className="h-4 w-4" />
                        Excluir
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="5" className="empty-cell">Nenhuma retirada encontrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
