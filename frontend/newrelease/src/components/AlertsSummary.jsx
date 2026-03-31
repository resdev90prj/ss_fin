import { AlertTriangle, BellRing, Clock3, Radar } from 'lucide-react';
import SectionCard from './SectionCard';
import { Badge } from './ui/badge';

function toneForItem(kind) {
  if (kind === 'overdue') {
    return 'danger';
  }
  if (kind === 'due_today') {
    return 'warning';
  }
  if (kind === 'due_soon') {
    return 'info';
  }
  return 'secondary';
}

export default function AlertsSummary({ executionCenter }) {
  const notifications = executionCenter?.notifications || [];
  const counts = executionCenter?.priority_counts || {
    critical: 0,
    high: 0,
    medium: 0,
    low: 0,
    no_deadline: 0,
  };

  return (
    <SectionCard
      title="Central de alertas"
      subtitle="Resumo operacional derivado das regras atuais do backend PHP."
      action={(
        <div className="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">
          <BellRing className="h-4 w-4 text-primary" />
          {notifications.length} eventos ativos
        </div>
      )}
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">
            <AlertTriangle className="h-4 w-4" />
            Critico
          </div>
          <p className="mt-3 text-2xl font-bold text-rose-800">{counts.critical || 0}</p>
        </div>
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
            <Clock3 className="h-4 w-4" />
            Hoje
          </div>
          <p className="mt-3 text-2xl font-bold text-amber-800">{counts.high || 0}</p>
        </div>
        <div className="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">
            <Radar className="h-4 w-4" />
            Proximos dias
          </div>
          <p className="mt-3 text-2xl font-bold text-blue-800">{counts.medium || 0}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Baixa</p>
          <p className="mt-3 text-2xl font-bold text-slate-800">{counts.low || 0}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sem prazo</p>
          <p className="mt-3 text-2xl font-bold text-slate-800">{counts.no_deadline || 0}</p>
        </div>
      </div>

      <div className="mt-5 space-y-3">
        {notifications.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
            Nenhuma notificacao operacional no momento.
          </p>
        ) : (
          notifications.slice(0, 5).map((item) => (
            <article key={`${item.kind}-${item.action_id}`} className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
              <div className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <strong className="text-sm font-semibold text-slate-950">{item.message}</strong>
                    <Badge variant={toneForItem(item.kind)}>{item.priority_label}</Badge>
                  </div>
                  <p className="text-sm text-slate-700">{item.action_title}</p>
                  <p className="text-xs text-slate-500">{item.objective_title || 'Sem objetivo'}</p>
                </div>
                <small className="shrink-0 text-right text-xs font-medium text-slate-500">
                  {item.urgency_text || 'Sem urgencia imediata'}
                </small>
              </div>
            </article>
          ))
        )}
      </div>
    </SectionCard>
  );
}
