import { useEffect, useState } from 'react';
import AlertsSummary from '../../components/AlertsSummary';
import HorizontalBarChart from '../../components/HorizontalBarChart';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import TrendLines from '../../components/TrendLines';
import { apiRequest } from '../../lib/apiClient';
import { currentMonthValue, formatCurrency, formatDate, formatNumber, formatPercent } from '../../lib/formatters';

export default function DashboardPage() {
  const [month, setMonth] = useState(currentMonthValue());
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [privacyMode, setPrivacyMode] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadDashboard() {
      setLoading(true);
      setError('');

      try {
        const response = await apiRequest('/dashboard/summary', {
          data: { month },
          signal: controller.signal,
        });

        setData(response.data);

        const storageKey = response.data?.privacy?.storage_key || 'dashboard_privacy_mode';
        const storedValue = window.localStorage.getItem(storageKey);
        setPrivacyMode(storedValue === 'on');
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Nao foi possivel carregar o dashboard.');
        }
      } finally {
        setLoading(false);
      }
    }

    loadDashboard();
    return () => controller.abort();
  }, [month]);

  function togglePrivacy() {
    const nextValue = !privacyMode;
    setPrivacyMode(nextValue);

    if (data?.privacy?.storage_key) {
      window.localStorage.setItem(data.privacy.storage_key, nextValue ? 'on' : 'off');
    }
  }

  if (loading) {
    return <LoadingState text="Montando os indicadores financeiros e a central de execucao." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Dashboard indisponivel</h2>
        <p>{error || 'Nao foi possivel carregar os dados do dashboard.'}</p>
      </section>
    );
  }

  const executionCenter = data.planning?.execution_center || {};
  const agenda = data.agenda || {};
  const weeklyScore = data.weekly_score || {};
  const agendaSummary = agenda.summary || {};
  const weeklyCurrent = weeklyScore.current_week || {};
  const planning = data.planning || {};
  const privateClass = privacyMode ? 'private-value private-value--hidden' : 'private-value';

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Resumo financeiro + execucao</span>
          <h1>Dashboard da nova release</h1>
          <p>
            Camada React em paralelo ao legado, consumindo JSON PHP e preservando a
            mesma logica de autenticacao, escopo e user_id.
          </p>
        </div>

        <div className="hero-card__actions">
          <label className="month-input">
            <span>Competencia</span>
            <input type="month" value={month} onChange={(event) => setMonth(event.target.value)} />
          </label>

          <button className="ghost-button" type="button" onClick={togglePrivacy}>
            {privacyMode ? 'Mostrar valores' : 'Ocultar valores'}
          </button>
        </div>
      </section>

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Saldo acumulado" value={formatCurrency(data.balance)} tone="neutral" privateValue />
        <StatCard label="Receitas" value={formatCurrency(data.summary?.incomes)} tone="positive" privateValue />
        <StatCard label="Despesas" value={formatCurrency(data.summary?.expenses)} tone="danger" privateValue />
        <StatCard label="Retiradas" value={formatCurrency(data.summary?.withdrawals)} tone="warning" privateValue />
        <StatCard
          label="Parcelas na competencia"
          value={formatCurrency(data.installment_projection?.total_scheduled)}
          hint={`Em aberto hoje: ${formatCurrency(data.installment_projection?.total_due)}`}
          tone="accent"
          privateValue
        />
        <StatCard
          label="Resultado da competencia"
          value={formatCurrency(data.projected_net)}
          hint={`Contexto: ${data.timeline_context}`}
          tone={Number(data.projected_net) >= 0 ? 'positive' : 'danger'}
          privateValue
        />
      </div>

      <div className="dashboard-grid">
        <SectionCard
          title="Central de execucao"
          subtitle="Foco no alvo ativo, agenda de hoje e score semanal."
        >
          {planning.active_target ? (
            <div className="stack-list">
              <article className="stack-item stack-item--accent">
                <div>
                  <strong>Alvo ativo</strong>
                  <p>{planning.active_target.title}</p>
                </div>
                <small>{formatPercent(planning.progress_percent)}</small>
              </article>

              <article className="stack-item">
                <div>
                  <strong>Objetivo atual</strong>
                  <p>{planning.active_objective?.title || 'Sem objetivo ativo'}</p>
                </div>
                <small>
                  {planning.objective_overdue
                    ? 'Objetivo atrasado'
                    : planning.objective_remaining_days ?? '-'} dias
                </small>
              </article>

              <article className="stack-item">
                <div>
                  <strong>Agenda prioritaria</strong>
                  <p>
                    {formatNumber(agendaSummary.overdue_count || 0)} atrasadas,{' '}
                    {formatNumber(agendaSummary.due_today_count || 0)} para hoje
                  </p>
                </div>
                <small>{formatNumber(agendaSummary.total || 0)} itens em aberto</small>
              </article>

              <article className="stack-item">
                <div>
                  <strong>Score semanal</strong>
                  <p>{weeklyCurrent.classification_label || 'Sem classificacao'}</p>
                </div>
                <small>{formatNumber(weeklyCurrent.score || 0)} pontos</small>
              </article>
            </div>
          ) : (
            <p className="muted-line">Nenhum alvo ativo no momento. O legado continua sendo a referencia principal ate a validacao desta release.</p>
          )}
        </SectionCard>

        <AlertsSummary executionCenter={executionCenter} />
      </div>

      <div className="dashboard-grid">
        <SectionCard title="Agenda de hoje" subtitle="Itens priorizados pela ordenacao do backend.">
          <div className="pill-grid">
            <div className="pill pill-danger">Atrasadas {agendaSummary.overdue_count || 0}</div>
            <div className="pill pill-warning">Hoje {agendaSummary.due_today_count || 0}</div>
            <div className="pill pill-accent">Ate 3 dias {agendaSummary.due_3_days_count || 0}</div>
            <div className="pill pill-neutral">Em andamento {agendaSummary.in_progress_count || 0}</div>
          </div>

          <div className="stack-list">
            {(agenda.focus_items || []).slice(0, 5).map((item) => (
              <article key={item.id} className="stack-item">
                <div>
                  <strong>{item.title}</strong>
                  <p>{item.objective_title || 'Sem objetivo'}</p>
                </div>
                <small>{item.urgency_text || 'Sem urgencia'}</small>
              </article>
            ))}

            {(agenda.focus_items || []).length === 0 ? (
              <p className="muted-line">Nenhum item urgente identificado para hoje.</p>
            ) : null}
          </div>
        </SectionCard>

        <SectionCard title="Score semanal" subtitle="Leitura rapida para acompanhamento operacional.">
          <div className="score-card">
            <div>
              <span>Score atual</span>
              <strong>{formatNumber(weeklyCurrent.score || 0)}</strong>
            </div>
            <div>
              <span>Classificacao</span>
              <strong>{weeklyCurrent.classification_label || 'Critico'}</strong>
            </div>
            <div>
              <span>Conclusao</span>
              <strong>{formatPercent(weeklyCurrent.completion_rate || 0)}</strong>
            </div>
          </div>

          <div className="stack-list">
            {(weeklyScore.history || []).slice(-4).reverse().map((week) => (
              <article key={week.week_start} className="stack-item">
                <div>
                  <strong>{week.week_label}</strong>
                  <p>{week.classification_label}</p>
                </div>
                <small>{formatNumber(week.score)} pontos</small>
              </article>
            ))}
          </div>
        </SectionCard>
      </div>

      <div className="dashboard-grid">
        <SectionCard title="Despesas por categoria" subtitle="Distribuicao da competencia atual.">
          <HorizontalBarChart items={data.expenses_by_category || []} />
        </SectionCard>

        <SectionCard title="Evolucao consolidada" subtitle="Receitas, despesas e parcelas previstas.">
          <TrendLines items={data.evolution || []} />
        </SectionCard>
      </div>

      <SectionCard title="Parcelas previstas na competencia" subtitle="Snapshot para validacao da camada React.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Divida</th>
                <th>Parcela</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Pago</th>
                <th>Saldo</th>
              </tr>
            </thead>
            <tbody>
              {(data.installment_details || []).slice(0, 8).map((item) => (
                <tr key={`${item.debt_id}-${item.installment_number}`}>
                  <td>{item.debt_description}</td>
                  <td>#{item.installment_number}</td>
                  <td>{formatDate(item.due_date)}</td>
                  <td className={privateClass}>{formatCurrency(item.amount)}</td>
                  <td className={privateClass}>{formatCurrency(item.paid_amount)}</td>
                  <td className={privateClass}>{formatCurrency(item.remaining_amount)}</td>
                </tr>
              ))}

              {(data.installment_details || []).length === 0 ? (
                <tr>
                  <td colSpan="6" className="empty-cell">Sem parcelas previstas para esta competencia.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}

