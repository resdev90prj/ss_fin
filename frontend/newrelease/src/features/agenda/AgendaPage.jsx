import { useEffect, useState } from 'react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { apiRequest } from '../../lib/apiClient';
import { formatDate, formatNumber } from '../../lib/formatters';

export default function AgendaPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();

    async function loadAgenda() {
      setLoading(true);
      setError('');

      try {
        const response = await apiRequest('/targets/summary', { signal: controller.signal });
        setData(response.data);
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Nao foi possivel carregar a agenda.');
        }
      } finally {
        setLoading(false);
      }
    }

    loadAgenda();
    return () => controller.abort();
  }, []);

  if (loading) {
    return <LoadingState text="Organizando sua agenda de execucao." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Agenda indisponivel</h2>
        <p>{error || 'Nao foi possivel carregar a agenda.'}</p>
      </section>
    );
  }

  const agenda = data.agenda || {};
  const summary = agenda.summary || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Agenda de execucao</span>
          <h1>Ordenacao diaria para foco operacional</h1>
          <p>Visualize prioridades do dia e acompanhe os itens que merecem resposta primeiro.</p>
        </div>
      </section>

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Total na agenda" value={formatNumber(summary.total)} />
        <StatCard label="Atrasadas" value={formatNumber(summary.overdue_count)} tone="danger" />
        <StatCard label="Vencem hoje" value={formatNumber(summary.due_today_count)} tone="warning" />
        <StatCard label="Ate 3 dias" value={formatNumber(summary.due_3_days_count)} tone="accent" />
        <StatCard label="Em andamento" value={formatNumber(summary.in_progress_count)} tone="neutral" />
        <StatCard label="Objetivo ativo" value={formatNumber(summary.active_objective_count)} tone="positive" />
      </div>

      <SectionCard title="Foco imediato" subtitle="Itens mais urgentes da agenda atual.">
        <div className="stack-list">
          {(agenda.focus_items || []).map((item) => (
            <article key={item.id} className="stack-item">
              <div>
                <strong>{item.title}</strong>
                <p>{item.objective_title || 'Sem objetivo'} | {item.target_title || 'Sem alvo'}</p>
              </div>
              <small>{item.urgency_text || item.priority_label || 'Sem urgencia'}</small>
            </article>
          ))}

          {(agenda.focus_items || []).length === 0 ? (
            <p className="muted-line">Nenhum item critico para hoje.</p>
          ) : null}
        </div>
      </SectionCard>

      <SectionCard title="Agenda completa" subtitle="Veja todas as acoes abertas em ordem de prioridade.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Acao</th>
                <th>Objetivo</th>
                <th>Prazo</th>
                <th>Status</th>
                <th>Urgencia</th>
              </tr>
            </thead>
            <tbody>
              {(agenda.items || []).map((item) => (
                <tr key={item.id}>
                  <td>{item.title}</td>
                  <td>{item.objective_title || '-'}</td>
                  <td>{formatDate(item.planned_date)}</td>
                  <td>{item.status_label || item.status}</td>
                  <td>{item.urgency_text || item.priority_label || '-'}</td>
                </tr>
              ))}

              {(agenda.items || []).length === 0 ? (
                <tr>
                  <td colSpan="5" className="empty-cell">Nenhuma acao aberta encontrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
