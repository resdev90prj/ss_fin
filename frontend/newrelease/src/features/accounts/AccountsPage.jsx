import { useEffect, useState } from 'react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatNumber } from '../../lib/formatters';

export default function AccountsPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();

    async function loadAccounts() {
      setLoading(true);
      setError('');

      try {
        const response = await apiRequest('/accounts', { signal: controller.signal });
        setData(response.data);
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Nao foi possivel carregar as contas.');
        }
      } finally {
        setLoading(false);
      }
    }

    loadAccounts();
    return () => controller.abort();
  }, []);

  if (loading) {
    return <LoadingState text="Carregando o cadastro de contas do usuario atual." />;
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
          <h1>Visao React das contas do legado</h1>
          <p>Esta tela valida leitura, isolamento por user_id e consistencia de status antes de migrarmos fluxos de escrita.</p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Total de contas" value={formatNumber(summary.total)} />
        <StatCard label="Ativas" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Inativas" value={formatNumber(summary.inactive_count)} tone="danger" />
        <StatCard label="Saldo inicial total" value={formatCurrency(summary.total_initial_balance)} tone="accent" />
      </div>

      <SectionCard title="Cadastro atual" subtitle="Leitura inicial do modulo de contas em JSON.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Conta</th>
                <th>Tipo</th>
                <th>Instituicao</th>
                <th>Saldo inicial</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((account) => (
                <tr key={account.id}>
                  <td>{account.name}</td>
                  <td>{account.type}</td>
                  <td>{account.institution || '-'}</td>
                  <td>{formatCurrency(account.initial_balance)}</td>
                  <td>{account.is_active ? 'Ativa' : 'Inativa'}</td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="5" className="empty-cell">Nenhuma conta encontrada para este usuario.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}

