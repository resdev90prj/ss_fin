import { useEffect, useState } from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import {
  AlertTriangle,
  ArrowDownRight,
  ArrowUpRight,
  CalendarClock,
  Eye,
  EyeOff,
  Goal,
  ShieldEllipsis,
  Sparkles,
  Target,
  Wallet,
} from 'lucide-react';
import AlertsSummary from '../../components/AlertsSummary';
import HorizontalBarChart from '../../components/HorizontalBarChart';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import SensitiveValue from '../../components/SensitiveValue';
import StatCard from '../../components/StatCard';
import TrendLines from '../../components/TrendLines';
import { Badge } from '../../components/ui/badge';
import { Progress } from '../../components/ui/progress';
import { Switch } from '../../components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { currentMonthValue, formatCurrency, formatDate, formatNumber, formatPercent } from '../../lib/formatters';
import OnboardingChecklist from '../../onboarding/OnboardingChecklist';

const weeklyColors = ['#155eef', '#3b82f6', '#06b6d4', '#22c55e', '#f59e0b', '#e11d48'];
const categoryColors = ['#155eef', '#3b82f6', '#06b6d4', '#039855', '#d97706', '#e11d48'];

function urgencyBadge(urgencyText) {
  const text = String(urgencyText || '').toLowerCase();
  if (text.includes('atras')) {
    return 'danger';
  }
  if (text.includes('hoje') || text.includes('amanha')) {
    return 'warning';
  }
  if (text.includes('dias')) {
    return 'info';
  }
  return 'secondary';
}

export default function DashboardPage() {
  const { session } = useAuth();
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

  function togglePrivacy(nextValue) {
    setPrivacyMode(nextValue);

    if (data?.privacy?.storage_key) {
      window.localStorage.setItem(data.privacy.storage_key, nextValue ? 'on' : 'off');
    }
  }

  if (loading) {
    return <LoadingState text="Organizando o panorama financeiro da sua competencia." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-slate-950">Dashboard indisponivel</h2>
          <p className="mt-1 text-sm text-slate-600">{error || 'Nao foi possivel carregar os dados do dashboard.'}</p>
        </div>
      </section>
    );
  }

  const planning = data.planning || {};
  const agenda = data.agenda || {};
  const weeklyScore = data.weekly_score || {};
  const executionCenter = planning.execution_center || {};
  const agendaSummary = agenda.summary || {};
  const weeklyCurrent = weeklyScore.current_week || {};
  const planningEnabled = Boolean(
    data.feature_flags?.planning_enabled
      ?? planning.enabled
      ?? session.permissions?.effective_modules?.includes('planning'),
  );
  const priorityCounts = executionCenter.priority_counts || {};
  const weeklyHistory = (weeklyScore.history || []).map((item, index) => ({
    ...item,
    fill: weeklyColors[index % weeklyColors.length],
  }));

  return (
    <div className="page-stack">
      <section className="hero-card bg-grid-slate">
        <div className="max-w-3xl">
          <span className="hero-card__eyebrow">Visao geral</span>
          <h1 className="font-display text-4xl tracking-tight text-slate-950 md:text-5xl">
            Tenha uma leitura clara do caixa, das prioridades e do plano atual.
          </h1>
          <p className="mt-4 max-w-2xl text-sm leading-7 text-slate-600 md:text-base">
            Acompanhe saldo, resultado, alertas e proximas acoes em uma visao feita para apoiar decisoes do dia.
          </p>
        </div>

        <div className="flex w-full max-w-md flex-col gap-4 rounded-[28px] border border-slate-200 bg-white/95 p-5 shadow-sm">
          <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Competencia ativa</p>
            <input type="month" value={month} onChange={(event) => setMonth(event.target.value)} />
          </div>

          <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="text-sm font-semibold text-slate-900">Modo privacidade</p>
                <p className="text-xs text-slate-500">Oculta valores sem comprometer a navegacao.</p>
              </div>
              <div className="flex items-center gap-3">
                {privacyMode ? <EyeOff className="h-4 w-4 text-slate-500" /> : <Eye className="h-4 w-4 text-slate-500" />}
                <Switch checked={privacyMode} onCheckedChange={togglePrivacy} />
              </div>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-2xl bg-slate-950 px-4 py-4 text-white">
              <p className="text-xs uppercase tracking-[0.18em] text-slate-300">Periodo</p>
              <p className="mt-3 text-xl font-bold">{data.month_label}</p>
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
              <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Contexto</p>
              <p className="mt-3 text-xl font-bold capitalize text-slate-950">{data.timeline_context}</p>
            </div>
          </div>
        </div>
      </section>

      <OnboardingChecklist />

      <div className="stats-grid stats-grid--wide">
        <StatCard
          className="xl:col-span-2"
          label="Saldo acumulado"
          value={formatCurrency(data.balance)}
          hint={`Receber ${formatCurrency(data.projected_receivable)} | Pagar ${formatCurrency(data.projected_payable)}`}
          tone="neutral"
          privateValue
          hidden={privacyMode}
          featured
          icon={<Wallet className="h-5 w-5" />}
        />
        <StatCard label="Receitas" value={formatCurrency(data.summary?.incomes)} tone="positive" privateValue hidden={privacyMode} icon={<ArrowUpRight className="h-5 w-5" />} />
        <StatCard label="Despesas" value={formatCurrency(data.summary?.expenses)} tone="danger" privateValue hidden={privacyMode} icon={<ArrowDownRight className="h-5 w-5" />} />
        <StatCard label="Retiradas" value={formatCurrency(data.summary?.withdrawals)} tone="warning" privateValue hidden={privacyMode} icon={<ShieldEllipsis className="h-5 w-5" />} />
        <StatCard label="Resultado" value={formatCurrency(data.projected_net)} tone={Number(data.projected_net) >= 0 ? 'positive' : 'danger'} privateValue hidden={privacyMode} icon={<Sparkles className="h-5 w-5" />} />
      </div>

      {planningEnabled ? (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,0.85fr)]">
          <SectionCard title="Central de execucao" subtitle="Priorize o que pede resposta agora e acompanhe o avanco do plano ativo.">
            {planning.active_target ? (
              <div className="space-y-5">
                <div className="rounded-[26px] bg-slate-950 p-6 text-white">
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                      <Badge variant="secondary" className="border-white/10 bg-white/10 text-slate-200">Alvo ativo</Badge>
                      <h3 className="mt-4 font-display text-3xl tracking-tight">{planning.active_target.title}</h3>
                      <p className="mt-2 text-sm leading-6 text-slate-300">{planning.active_objective?.title || 'Sem objetivo ativo definido no momento.'}</p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-right">
                      <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Progresso</p>
                      <p className="mt-3 text-3xl font-bold">{formatPercent(planning.progress_percent)}</p>
                    </div>
                  </div>

                  <div className="mt-5 space-y-3">
                    <Progress value={planning.progress_percent} className="bg-white/10" indicatorClassName="bg-white" />
                    <div className="grid gap-3 text-sm text-slate-300 sm:grid-cols-3">
                      <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Pendentes</p>
                        <p className="mt-2 text-2xl font-bold text-white">{formatNumber(planning.pending_actions)}</p>
                      </div>
                      <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Concluidas</p>
                        <p className="mt-2 text-2xl font-bold text-white">{formatNumber(planning.done_actions)}</p>
                      </div>
                      <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Objetivo</p>
                        <p className="mt-2 text-2xl font-bold text-white">{planning.objective_overdue ? 'Atrasado' : `${planning.objective_remaining_days ?? '-'}d`}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="rounded-[26px] border border-rose-200 bg-rose-50 p-5">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">
                      <AlertTriangle className="h-4 w-4" />
                      Acoes criticas
                    </div>
                    <p className="mt-4 text-4xl font-extrabold tracking-tight text-rose-800">{priorityCounts.critical || 0}</p>
                    <p className="mt-2 text-sm text-rose-700">Itens atrasados ou ja pressionando a execucao.</p>
                  </div>

                  <div className="rounded-[26px] border border-blue-200 bg-blue-50 p-5">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">
                      <Goal className="h-4 w-4" />
                      Prioridade tatica
                    </div>
                    <p className="mt-4 text-4xl font-extrabold tracking-tight text-blue-800">{(priorityCounts.high || 0) + (priorityCounts.medium || 0)}</p>
                    <p className="mt-2 text-sm text-blue-700">Acoes para hoje e proximos dias.</p>
                  </div>
                </div>

                <div className="space-y-3">
                  {(executionCenter.immediate_attention || []).slice(0, 4).map((action) => (
                    <article key={action.id} className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <strong className="text-sm font-semibold text-slate-950">{action.title}</strong>
                          <p className="mt-1 text-sm text-slate-600">{action.objective_title || 'Sem objetivo'}</p>
                        </div>
                        <Badge variant={urgencyBadge(action.urgency_text)}>{action.priority_label}</Badge>
                      </div>
                      <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                        <span>{action.urgency_text || 'Sem urgencia'}</span>
                        <span>{formatDate(action.planned_date)}</span>
                      </div>
                    </article>
                  ))}
                </div>
              </div>
            ) : (
              <div className="rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <Target className="mx-auto h-8 w-8 text-slate-400" />
                <h3 className="mt-4 text-lg font-semibold text-slate-900">Nenhum alvo ativo no momento</h3>
                <p className="mt-2 text-sm text-slate-600">
                  Quando um alvo for ativado, esta area passa a destacar automaticamente progresso, prioridades e risco.
                </p>
              </div>
            )}
          </SectionCard>

          <div className="space-y-6">
            <AlertsSummary executionCenter={executionCenter} />

            <SectionCard title="Agenda de hoje" subtitle="Itens priorizados para orientar o foco do dia." action={<Badge variant="outline">{formatNumber(agendaSummary.total || 0)} itens</Badge>}>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Atrasadas</p>
                  <p className="mt-2 text-3xl font-bold text-rose-800">{formatNumber(agendaSummary.overdue_count || 0)}</p>
                </div>
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Hoje</p>
                  <p className="mt-2 text-3xl font-bold text-amber-800">{formatNumber(agendaSummary.due_today_count || 0)}</p>
                </div>
              </div>

              <div className="mt-5 space-y-3">
                {(agenda.focus_items || []).slice(0, 4).map((item) => (
                  <article key={item.id} className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <strong className="text-sm font-semibold text-slate-950">{item.title}</strong>
                        <p className="mt-1 text-sm text-slate-600">{item.objective_title || 'Sem objetivo'} | {item.target_title || 'Sem alvo'}</p>
                      </div>
                      <Badge variant={urgencyBadge(item.urgency_text || item.priority_label)}>{item.priority_label}</Badge>
                    </div>
                    <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                      <span>{item.urgency_text || 'Sem urgencia'}</span>
                      <span>{formatDate(item.planned_date)}</span>
                    </div>
                  </article>
                ))}
              </div>
            </SectionCard>
          </div>
        </div>
      ) : (
        <div className="grid gap-6 xl:grid-cols-2">
          <SectionCard title="Planejamento sob demanda" subtitle="Este acesso esta concentrado em dashboard e operacao financeira.">
            <div className="rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
              <Target className="mx-auto h-8 w-8 text-slate-400" />
              <h3 className="mt-4 text-lg font-semibold text-slate-900">Planejamento desabilitado para este usuario</h3>
              <p className="mt-2 text-sm text-slate-600">
                Metas, alvos, agenda de execucao e score semanal podem ser liberados depois sem alterar o restante do painel.
              </p>
            </div>
          </SectionCard>

          <SectionCard title="Leitura financeira continua ativa" subtitle="Saldo, resultado, categorias e vencimentos seguem disponiveis normalmente.">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Receber</p>
                <p className="mt-3 text-2xl font-bold text-slate-950"><SensitiveValue hidden={privacyMode}>{formatCurrency(data.projected_receivable)}</SensitiveValue></p>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Pagar</p>
                <p className="mt-3 text-2xl font-bold text-slate-950"><SensitiveValue hidden={privacyMode}>{formatCurrency(data.projected_payable)}</SensitiveValue></p>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-white px-4 py-4 sm:col-span-2">
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Parcelas na competencia</p>
                <p className="mt-3 text-2xl font-bold text-slate-950">{formatNumber(data.installment_projection?.installments_count || 0)}</p>
                <p className="mt-2 text-sm text-slate-600">O acompanhamento financeiro segue estavel mesmo sem os blocos de planejamento habilitados.</p>
              </div>
            </div>
          </SectionCard>
        </div>
      )}

      <SectionCard title="Painel visual da competencia" subtitle="Graficos e indicadores para leitura gerencial rapida.">
        <Tabs defaultValue="cashflow">
          <TabsList>
            <TabsTrigger value="cashflow">Fluxo</TabsTrigger>
            <TabsTrigger value="categories">Categorias</TabsTrigger>
            {planningEnabled ? <TabsTrigger value="score">Score semanal</TabsTrigger> : null}
          </TabsList>

          <TabsContent value="cashflow">
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
              <div className="rounded-[28px] border border-slate-200 bg-white p-4">
                <TrendLines items={data.evolution || []} />
              </div>
              <div className="space-y-4">
                <div className="rounded-[28px] border border-slate-200 bg-slate-50 p-5">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Resultado projetado</p>
                  <div className="mt-4 text-3xl font-extrabold tracking-tight text-slate-950">
                    <SensitiveValue hidden={privacyMode}>{formatCurrency(data.projected_net)}</SensitiveValue>
                  </div>
                  <p className="mt-2 text-sm leading-6 text-slate-600">Com base na receita prevista, nas despesas, retiradas e parcelas da competencia.</p>
                </div>

                <div className="rounded-[28px] border border-slate-200 bg-white p-5">
                  <h4 className="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Distribuicao por categoria</h4>
                  <div className="mt-4">
                    <HorizontalBarChart items={data.expenses_by_category || []} />
                  </div>
                </div>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="categories">
            <div className="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
              <div className="rounded-[28px] border border-slate-200 bg-white p-4">
                <div className="h-[340px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie data={data.expenses_by_category || []} dataKey="total" nameKey="name" cx="50%" cy="50%" innerRadius={70} outerRadius={110} paddingAngle={4}>
                        {(data.expenses_by_category || []).map((entry, index) => (
                          <Cell key={entry.name} fill={categoryColors[index % categoryColors.length]} />
                        ))}
                      </Pie>
                      <Tooltip formatter={(value) => formatCurrency(value)} />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
              </div>

              <div className="space-y-3">
                {(data.expenses_by_category || []).slice(0, 6).map((item, index) => (
                  <article key={item.name} className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                    <div className="flex items-center justify-between gap-4">
                      <div className="flex items-center gap-3">
                        <span className="h-3.5 w-3.5 rounded-full" style={{ backgroundColor: categoryColors[index % categoryColors.length] }} />
                        <div>
                          <strong className="text-sm font-semibold text-slate-950">{item.name}</strong>
                          <p className="text-xs text-slate-500">Despesa da competencia atual</p>
                        </div>
                      </div>
                      <span className="text-sm font-semibold text-slate-900"><SensitiveValue hidden={privacyMode}>{formatCurrency(item.total)}</SensitiveValue></span>
                    </div>
                  </article>
                ))}
              </div>
            </div>
          </TabsContent>

          {planningEnabled ? (
            <TabsContent value="score">
              <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                <div className="rounded-[28px] border border-slate-200 bg-white p-4">
                  <div className="h-[340px]">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={weeklyHistory} margin={{ top: 12, right: 18, left: 6, bottom: 8 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" vertical={false} />
                        <XAxis dataKey="week_label" tick={{ fill: '#475467', fontSize: 12 }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fill: '#475467', fontSize: 12 }} axisLine={false} tickLine={false} />
                        <Tooltip formatter={(value) => `${value} pts`} />
                        <Bar dataKey="score" radius={[12, 12, 4, 4]}>
                          {weeklyHistory.map((item, index) => (
                            <Cell key={item.week_start} fill={item.fill || weeklyColors[index % weeklyColors.length]} />
                          ))}
                        </Bar>
                      </BarChart>
                    </ResponsiveContainer>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="rounded-[28px] border border-slate-200 bg-slate-950 p-5 text-white">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Score atual</p>
                    <p className="mt-4 text-5xl font-extrabold tracking-tight">{formatNumber(weeklyCurrent.score || 0)}</p>
                    <p className="mt-2 text-sm text-slate-300">{weeklyCurrent.classification_label || 'Sem classificacao'}</p>
                  </div>

                  <div className="rounded-[28px] border border-slate-200 bg-white p-5">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                      <CalendarClock className="h-4 w-4 text-primary" />
                      Semana atual
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-3">
                      <div className="rounded-2xl bg-slate-50 px-4 py-3">
                        <p className="text-xs text-slate-500">Concluidas</p>
                        <p className="mt-2 text-2xl font-bold text-slate-950">{formatNumber(weeklyCurrent.completed_count || 0)}</p>
                      </div>
                      <div className="rounded-2xl bg-slate-50 px-4 py-3">
                        <p className="text-xs text-slate-500">Previstas</p>
                        <p className="mt-2 text-2xl font-bold text-slate-950">{formatNumber(weeklyCurrent.planned_count || 0)}</p>
                      </div>
                      <div className="rounded-2xl bg-slate-50 px-4 py-3">
                        <p className="text-xs text-slate-500">Taxa</p>
                        <p className="mt-2 text-2xl font-bold text-slate-950">{formatPercent(weeklyCurrent.completion_rate || 0)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>
          ) : null}
        </Tabs>
      </SectionCard>

      <SectionCard title="Parcelas previstas na competencia" subtitle="Acompanhe vencimentos previstos da competencia com visao consolidada.">
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
              {(data.installment_details || []).slice(0, 10).map((item) => (
                <tr key={`${item.debt_id}-${item.installment_number}`}>
                  <td><strong>{item.debt_description}</strong></td>
                  <td>#{item.installment_number}</td>
                  <td>{formatDate(item.due_date)}</td>
                  <td><SensitiveValue hidden={privacyMode}>{formatCurrency(item.amount)}</SensitiveValue></td>
                  <td><SensitiveValue hidden={privacyMode}>{formatCurrency(item.paid_amount)}</SensitiveValue></td>
                  <td><SensitiveValue hidden={privacyMode}>{formatCurrency(item.remaining_amount)}</SensitiveValue></td>
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

      {error ? <div className="alert-banner alert-banner--danger">{error}</div> : null}
    </div>
  );
}
