import { useEffect, useState } from 'react';
import { PiggyBank, Trash2 } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatNumber } from '../../lib/formatters';

const emptyForm = {
  category_id: '',
  month_ref: new Date().toISOString().slice(0, 7),
  amount_limit: '',
};

export default function BudgetsPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [submitting, setSubmitting] = useState(false);

  async function loadBudgets(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/budgets', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar os orcamentos.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadBudgets(controller.signal);
    return () => controller.abort();
  }, []);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      await apiRequest('/budgets', {
        method: 'POST',
        data: {
          ...form,
          amount_limit: Number(form.amount_limit || 0),
          category_id: Number(form.category_id || 0),
          csrf_token: session.csrf_token,
        },
      });

      setNotice('Orcamento salvo com sucesso.');
      setForm(emptyForm);
      await loadBudgets();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar o orcamento.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(budgetId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/budgets/delete', {
        method: 'POST',
        data: {
          id: budgetId,
          csrf_token: session.csrf_token,
        },
      });

      setNotice('Orcamento removido com sucesso.');
      await loadBudgets();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel remover o orcamento.');
    }
  }

  function useExistingBudget(budget) {
    setForm({
      category_id: String(budget.category_id),
      month_ref: budget.month_ref || new Date().toISOString().slice(0, 7),
      amount_limit: String(budget.amount_limit ?? ''),
    });
  }

  if (loading && !data) {
    return <LoadingState text="Carregando os limites mensais por categoria." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Orcamentos indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar os orcamentos.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const categories = data.lookups?.categories || [];

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Orcamentos</span>
          <h1>Defina limites mensais por categoria sem sair do produto.</h1>
          <p>
            A interface agora cobre a manutencao real dos orcamentos, preservando
            o upsert do backend PHP e a mesma base de categorias do sistema.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Orcamentos" value={formatNumber(summary.total)} icon={<PiggyBank className="h-5 w-5" />} />
        <StatCard label="Mes atual" value={formatNumber(summary.current_month_count)} tone="accent" />
        <StatCard label="Limite total" value={formatCurrency(summary.total_limit)} tone="positive" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title="Salvar orcamento" subtitle="Salvar novamente a mesma categoria e mes atualiza o limite existente.">
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleSubmit}>
          <label>
            <span>Categoria</span>
            <select name="category_id" value={form.category_id} onChange={handleChange} required>
              <option value="">Selecione</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Mes de referencia</span>
            <input name="month_ref" type="month" value={form.month_ref} onChange={handleChange} required />
          </label>

          <label>
            <span>Limite</span>
            <input name="amount_limit" type="number" step="0.01" value={form.amount_limit} onChange={handleChange} required />
          </label>

          <div className="flex items-end">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : 'Salvar orcamento'}
            </Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Mapa de limites" subtitle="Clique em um item para reutilizar a combinacao categoria + mes no formulario acima.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Mes</th>
                <th>Limite</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((budget) => (
                <tr key={budget.id}>
                  <td>
                    <strong>{budget.category_name}</strong>
                    <small>ID {budget.id}</small>
                  </td>
                  <td>{budget.month_ref}</td>
                  <td>{formatCurrency(budget.amount_limit)}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => useExistingBudget(budget)}>
                        Reusar no formulario
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleDelete(budget.id)}>
                        <Trash2 className="h-4 w-4" />
                        Excluir
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="4" className="empty-cell">Nenhum orcamento cadastrado.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
