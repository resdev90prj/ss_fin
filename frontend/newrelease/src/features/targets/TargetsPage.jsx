import { useEffect, useState } from 'react';
import AlertsSummary from '../../components/AlertsSummary';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber, formatPercent } from '../../lib/formatters';

export default function TargetsPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();

    async function loadSummary() {
      setLoading(true);
      setError('');

      try {
        const response = await apiRequest('/targets/summary', { signal: controller.signal });
        setData(response.data);
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Nao foi possivel carregar o resumo de execucao.');
        }
      } finally {
        setLoading(false);
      }
    }

    loadSummary();
    return () => controller.abort();
  }, []);

  if (loading) {
    return <LoadingState text="Carregando seu plano, agenda e score semanal." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Execucao indisponivel</h2>
        <p>{error || 'Nao foi possivel carregar o resumo de execucao.'}</p>
      </section>
    );
  }

  const planning = data.planning || {};
  const executionCenter = planning.execution_center || {};
  const weekly = data.weekly_score?.current_week || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Alvos, objetivos e execucao</span>
          <h1>Resumo estrategico do plano atual</h1>
          <p>Acompanhe avanco, prioridades e proximos passos do plano em uma visao objetiva.</p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Progresso do alvo" value={formatPercent(planning.progress_percent)} tone="accent" />
        <StatCard label="Acoes pendentes" value={formatNumber(planning.pending_actions)} tone="warning" />
        <StatCard label="Acoes concluidas" value={formatNumber(planning.done_actions)} tone="positive" />
        <StatCard label="Score semanal" value={formatNumber(weekly.score)} tone="neutral" />
      </div>

      <div className="dashboard-grid">
        <SectionCard title="Plano ativo" subtitle="Visao resumida do plano em execucao.">
          {planning.active_target ? (
            <div className="stack-list">
              <article className="stack-item stack-item--accent">
                <div>
                  <strong>{planning.active_target.title}</strong>
                  <p>Alvo ativo</p>
                </div>
                <small>{formatPercent(planning.progress_percent)}</small>
              </article>

              <article className="stack-item">
                <div>
                  <strong>{planning.active_objective?.title || 'Sem objetivo ativo'}</strong>
                  <p>Objetivo atual</p>
                </div>
                <small>
                  {planning.objective_overdue
                    ? 'Atrasado'
                    : `${planning.objective_remaining_days ?? '-'} dias`}
                </small>
              </article>
            </div>
          ) : (
            <p className="muted-line">Nenhum alvo ativo configurado para o usuario atual.</p>
          )}
        </SectionCard>

        <AlertsSummary executionCenter={executionCenter} />
      </div>

      <SectionCard title="Proximas acoes" subtitle="Itens com maior prioridade no momento.">
        <div className="stack-list">
          {(planning.next_actions || []).map((action) => (
            <article key={action.id} className="stack-item">
              <div>
                <strong>{action.title}</strong>
                <p>{action.objective_title || 'Sem objetivo'}</p>
              </div>
              <small>{action.urgency_text || action.priority_label || 'Sem urgencia'}</small>
            </article>
          ))}

          {(planning.next_actions || []).length === 0 ? (
            <p className="muted-line">Sem proximas acoes pendentes no alvo ativo.</p>
          ) : null}
        </div>
      </SectionCard>
    </div>
  );
}
