import { useEffect, useState } from 'react';
import { Archive } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatDate, formatNumber } from '../../lib/formatters';

const initialFilters = {
  from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  to: new Date().toISOString().slice(0, 10),
  type: '',
  category_id: '',
  account_id: '',
};

export default function ReportsPage() {
  const [filters, setFilters] = useState(initialFilters);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadReport(nextFilters = filters, signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/reports', {
        data: nextFilters,
        signal,
      });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar o relatorio.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadReport(filters, controller.signal);
    return () => controller.abort();
  }, []);

  function handleChange(event) {
    const { name, value } = event.target;
    setFilters((current) => ({
      ...current,
      [name]: value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    await loadReport(filters);
  }

  if (loading && !data) {
    return <LoadingState text="Gerando a visao consolidada do periodo selecionado." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Relatorios indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar o relatorio.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const lookups = data.lookups || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Relatorios</span>
          <h1>Analise o periodo com filtros consistentes e resultado liquido consolidado.</h1>
          <p>
            O modulo de relatorios agora roda integralmente nesta interface, com a mesma base
            operacional das transacoes e uma navegacao continua dentro do produto.
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

        <div className="flex items-end">
          <Button type="submit">Atualizar relatorio</Button>
        </div>
      </form>

      {error ? <div className="alert-banner alert-banner--danger">{error}</div> : null}

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Lancamentos" value={formatNumber(summary.total)} icon={<Archive className="h-5 w-5" />} />
        <StatCard label="Receitas" value={formatCurrency(summary.income_total)} tone="positive" />
        <StatCard label="Despesas" value={formatCurrency(summary.expense_total)} tone="danger" />
        <StatCard label="Retiradas" value={formatCurrency(summary.withdrawal_total)} tone="warning" />
        <StatCard label="Transferencias" value={formatCurrency(summary.transfer_total)} tone="accent" />
        <StatCard label="Resultado liquido" value={formatCurrency(summary.net_total)} tone={Number(summary.net_total) >= 0 ? 'positive' : 'danger'} />
      </div>

      <SectionCard title="Lancamentos do periodo" subtitle="Visao tabular detalhada para auditoria rapida do resultado.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Descricao</th>
                <th>Categoria</th>
                <th>Conta</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((transaction) => (
                <tr key={transaction.id}>
                  <td>{formatDate(transaction.transaction_date)}</td>
                  <td>{transaction.type}</td>
                  <td>
                    <strong>{transaction.description}</strong>
                    <small>{transaction.payment_method || 'Sem forma de pagamento'}</small>
                  </td>
                  <td>{transaction.category_name}</td>
                  <td>{transaction.account_name}</td>
                  <td>{formatCurrency(transaction.amount)}</td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Nenhum lancamento encontrado para os filtros atuais.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
