import { ArrowUpRight, CheckCircle2, ShieldCheck, Sparkles } from 'lucide-react';
import SectionCard from '../../components/SectionCard';
import { useAuth } from '../../context/AuthContext';
import { buildLegacyHref } from '../../navigation/menu';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';

export default function ModulePlaceholderPage({ module }) {
  const { session } = useAuth();

  if (module?.adminOnly && !session.scope?.is_admin) {
    return (
      <div className="page-stack">
        <section className="state-card">
          <div>
            <h2 className="text-lg font-bold tracking-tight text-slate-950">Acesso restrito</h2>
            <p className="mt-1 text-sm text-slate-600">
              Este modulo existe no legado, mas continua reservado a usuarios admin.
            </p>
          </div>
        </section>
      </div>
    );
  }

  const legacyHref = buildLegacyHref(session.release?.legacy_base, module?.legacyRoute);
  const Icon = module?.icon || Sparkles;

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
          <div className="max-w-3xl">
            <span className="hero-card__eyebrow">Modulo preservado na migracao</span>
            <h1>{module?.label || 'Modulo em evolucao'}</h1>
            <p>
              Este modulo ja existe no sistema legado PHP e foi mantido na navegacao React para
              evitar regressao de produto. A interface dedicada desta area ainda esta em evolucao.
            </p>
          </div>

          <div className="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <Icon className="h-5 w-5" />
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                <div className="mt-2 flex items-center gap-2">
                  <Badge variant="warning">Em evolucao</Badge>
                  <Badge variant="outline">Sem perda funcional</Badge>
                </div>
              </div>
            </div>

            <div className="mt-5 space-y-3 text-sm text-slate-600">
              <p>{module?.description || 'Modulo preservado enquanto a migracao React avanca.'}</p>
              <p>
                O backend, as regras de negocio e o fluxo validado seguem disponiveis no legado.
              </p>
            </div>

            <div className="mt-5 flex flex-wrap gap-3">
              <Button asChild>
                <a href={legacyHref} target="_self" rel="noreferrer">
                  Abrir no legado
                  <ArrowUpRight className="h-4 w-4" />
                </a>
              </Button>
            </div>
          </div>
        </div>
      </section>

      <SectionCard
        title="Cobertura funcional preservada"
        subtitle="A navegacao React agora expone o modulo para que nenhuma capacidade do legado fique oculta."
      >
        <div className="grid gap-4 lg:grid-cols-3">
          <article className="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
              <CheckCircle2 className="h-4 w-4" />
              Disponivel hoje
            </div>
            <p className="mt-4 text-sm leading-7 text-emerald-900">
              O modulo segue acessivel no fluxo PHP atual, com as regras e validacoes ja aprovadas em producao.
            </p>
          </article>

          <article className="rounded-3xl border border-blue-200 bg-blue-50 p-5">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">
              <Sparkles className="h-4 w-4" />
              Ponte React
            </div>
            <p className="mt-4 text-sm leading-7 text-blue-900">
              A rota React existe para espelhar o mapa completo do produto e sinalizar a evolucao da nova interface.
            </p>
          </article>

          <article className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600">
              <ShieldCheck className="h-4 w-4" />
              Sem duplicar logica
            </div>
            <p className="mt-4 text-sm leading-7 text-slate-700">
              Nenhuma regra foi recriada no frontend. Quando esta tela for migrada, continuaremos consumindo o backend PHP.
            </p>
          </article>
        </div>
      </SectionCard>
    </div>
  );
}
