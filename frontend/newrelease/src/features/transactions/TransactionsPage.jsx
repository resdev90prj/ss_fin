import { useEffect, useState } from 'react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatDate, formatNumber } from '../../lib/formatters';

const initialFilters = {
  from: '',
  to: '',
  type: '',
  category_id: '',
  account_id: '',
  prioritize_others: true,
};

export default function TransactionsPage() {
  const [filters, setFilters] = useState(initialFilters);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadTransactions(nextFilters, signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/transactions', {
        data: nextFilters,
        signal,
      });

      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as transacoes.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadTransactions(filters, controller.signal);
    return () => controller.abort();
  }, []);

  function handleChange(event) {
    const { name, value, type, checked } = event.target;
    setFilters((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    await loadTransactions(filters);
  }

  if (loading && !data) {
    return <LoadingState text="Consultando as transacoes do usuario e os filtros disponiveis." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Transacoes indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as transacoes.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const lookups = data.lookups || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Transacoes</span>
          <h1>Leitura inicial do fluxo financeiro</h1>
          <p>
            Esta etapa valida filtros, ordenacao priorizando "Outros gastos" e a
            mesma leitura do backend antes de migrar formularios completos.
          </p>
        </div>
      </section>

      <form className="filter-card" onSubmit={handleSubmit}>
        <label>
          <span>De</span>
          <input type="date" name="from" value={filters.from} onChange={handleChange} />
        </label>

        <label>
          <span>Ate</span>
          <input type="date" name="to" value={filters.to} onChange={handleChange} />
        </label>

        <label>
          <span>Tipo</span>
          <select name="type" value={filters.type} onChange={handleChange}>
            <option value="">Todos</option>
            <option value="income">Receita</option>
            <option value="expense">Despesa</option>
            <option value="partner_withdrawal">Retirada</option>
            <option value="transfer">Transferencia</option>
          </select>
        </label>

        <label>
          <span>Conta</span>
          <select name="account_id" value={filters.account_id} onChange={handleChange}>
            <option value="">Todas</option>
            {(lookups.accounts || []).map((account) => (
              <option key={account.id} value={account.id}>
                {account.name}
              </option>
            ))}
          </select>
        </label>

        <label>
          <span>Categoria</span>
          <select name="category_id" value={filters.category_id} onChange={handleChange}>
            <option value="">Todas</option>
            {(lookups.categories || []).map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </label>

        <label className="checkbox-line">
          <input
            type="checkbox"
            name="prioritize_others"
            checked={filters.prioritize_others}
            onChange={handleChange}
          />
          <span>Priorizar "Outros gastos"</span>
        </label>

        <button className="solid-button" type="submit">
          Aplicar filtros
        </button>
      </form>

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Lancamentos" value={formatNumber(summary.total)} />
        <StatCard label="Receitas" value={formatCurrency(summary.income_total)} tone="positive" />
        <StatCard label="Despesas" value={formatCurrency(summary.expense_total)} tone="danger" />
        <StatCard label="Retiradas" value={formatCurrency(summary.withdrawal_total)} tone="warning" />
        <StatCard label="Transferencias" value={formatCurrency(summary.transfer_total)} tone="accent" />
        <StatCard label="Outros gastos pendentes" value={formatNumber(summary.others_pending_count)} tone="neutral" />
      </div>

      {error ? <div className="alert-banner alert-banner--danger">{error}</div> : null}

      <SectionCard title="Grid operacional" subtitle="Primeiro espelho React do modulo de transacoes.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Descricao</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Categoria</th>
                <th>Conta</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((transaction) => (
                <tr key={transaction.id} className={transaction.is_others_category ? 'row-highlight' : ''}>
                  <td>
                    <strong>{transaction.description}</strong>
                    <small>{transaction.payment_method || 'Sem forma de pagamento'}</small>
                  </td>
                  <td>{transaction.type}</td>
                  <td>{formatCurrency(transaction.amount)}</td>
                  <td>{transaction.category_name}</td>
                  <td>{transaction.account_name}</td>
                  <td>{formatDate(transaction.transaction_date)}</td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Nenhuma transacao encontrada para os filtros atuais.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}

