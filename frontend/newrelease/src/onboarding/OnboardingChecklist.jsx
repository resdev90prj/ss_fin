import { ArrowRight, CheckCircle2, CirclePlay } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import SectionCard from '../components/SectionCard';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Progress } from '../components/ui/progress';
import { cn } from '../lib/utils';
import { useOnboarding } from './OnboardingProvider';

export default function OnboardingChecklist() {
  const navigate = useNavigate();
  const { checklist, startTour } = useOnboarding();

  return (
    <SectionCard
      title="Comece por aqui"
      action={(
        <Button type="button" variant="outline" size="sm" onClick={() => startTour(0)}>
          <CirclePlay className="h-4 w-4" />
          Ver tour
        </Button>
      )}
      contentClassName="space-y-5"
    >
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-sm font-semibold text-slate-950">
            {checklist.completedCount}/{checklist.totalCount} concluido
          </p>
          <p className="mt-1 text-sm text-slate-600">
            Siga o proximo passo para colocar o sistema em uso.
          </p>
        </div>

        <div className="w-full max-w-sm">
          <Progress value={checklist.percent} />
        </div>
      </div>

      {checklist.error ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {checklist.error}
        </div>
      ) : null}

      <div className="grid gap-3">
        {checklist.items.map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => navigate(item.path)}
            className={cn(
              'flex w-full items-center justify-between gap-4 rounded-[24px] border px-4 py-4 text-left transition-all',
              item.completed
                ? 'border-emerald-200 bg-emerald-50/70'
                : item.isNextStep
                  ? 'border-blue-200 bg-blue-50/80 shadow-sm'
                  : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50',
            )}
          >
            <div className="flex min-w-0 items-start gap-3">
              {item.completed ? (
                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
              ) : (
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-300">
                  <span className="h-2 w-2 rounded-full bg-slate-300" />
                </span>
              )}

              <div className="min-w-0">
                <p className="text-sm font-semibold text-slate-950">{item.label}</p>
                <p className="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">
                  Abrir {item.moduleLabel}
                </p>
              </div>
            </div>

            <div className="flex shrink-0 items-center gap-2">
              {item.isNextStep ? <Badge variant="info">Proximo passo</Badge> : null}
              {item.completed ? <Badge variant="success">Concluido</Badge> : null}
              <ArrowRight className="h-4 w-4 text-slate-400" />
            </div>
          </button>
        ))}
      </div>

      {checklist.completedCount === checklist.totalCount ? (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
          Base inicial concluida. Agora e seguir operando no ritmo do negocio.
        </div>
      ) : null}
    </SectionCard>
  );
}
