import { useEffect, useState } from 'react';
import { Goal, Pencil, Trash2, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatNumber, formatPercent } from '../../lib/formatters';

const emptyForm = {
  title: '',
  target_amount: '',
  current_amount: '0',
  target_date: '',
  status: 'active',
};

export default function GoalsPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function loadGoals(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/goals', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as metas.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadGoals(controller.signal);
    return () => controller.abort();
  }, []);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleEdit(goal) {
    setEditingId(goal.id);
    setForm({
      title: goal.title || '',
      target_amount: String(goal.target_amount ?? ''),
      current_amount: String(goal.current_amount ?? 0),
      target_date: goal.target_date || '',
      status: goal.status || 'active',
    });
    setError('');
    setNotice('');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      await apiRequest(editingId ? '/goals/update' : '/goals', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          target_amount: Number(form.target_amount || 0),
          current_amount: Number(form.current_amount || 0),
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Meta atualizada com sucesso.' : 'Meta criada com sucesso.');
      resetForm();
      await loadGoals();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar a meta.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(goalId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/goals/delete', {
        method: 'POST',
        data: {
          id: goalId,
          csrf_token: session.csrf_token,
        },
      });

      if (editingId === goalId) {
        resetForm();
      }

      setNotice('Meta removida com sucesso.');
      await loadGoals();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel remover a meta.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando as metas financeiras e seus progressos." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Metas indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as metas.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Metas financeiras</span>
          <h1>Transforme objetivos financeiros em acompanhamento continuo.</h1>
          <p>
            Crie metas, acompanhe a evolucao e ajuste valores ou prazos quando precisar.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Metas" value={formatNumber(summary.total)} icon={<Goal className="h-5 w-5" />} />
        <StatCard label="Ativas" value={formatNumber(summary.active_count)} tone="positive" />
        <StatCard label="Concluidas" value={formatNumber(summary.completed_count)} tone="accent" />
        <StatCard label="Valor alvo total" value={formatCurrency(summary.target_total)} tone="neutral" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title={editingId ? 'Editar meta' : 'Nova meta'} subtitle="Defina valor alvo, valor atual, prazo e status da meta.">
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-5" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Titulo</span>
            <input name="title" value={form.title} onChange={handleChange} placeholder="Ex.: Reserva de emergencia" required />
          </label>

          <label>
            <span>Valor alvo</span>
            <input name="target_amount" type="number" step="0.01" value={form.target_amount} onChange={handleChange} required />
          </label>

          <label>
            <span>Valor atual</span>
            <input name="current_amount" type="number" step="0.01" value={form.current_amount} onChange={handleChange} />
          </label>

          <label>
            <span>Data alvo</span>
            <input name="target_date" type="date" value={form.target_date} onChange={handleChange} />
          </label>

          <label>
            <span>Status</span>
            <select name="status" value={form.status} onChange={handleChange}>
              <option value="active">Ativa</option>
              <option value="completed">Concluida</option>
              <option value="paused">Pausada</option>
            </select>
          </label>

          <div className="flex items-end gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Criar meta'}
            </Button>
            {editingId ? (
              <Button type="button" variant="outline" onClick={resetForm}>
                <X className="h-4 w-4" />
                Cancelar
              </Button>
            ) : null}
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Carteira de metas" subtitle="Acompanhamento visual do valor atual contra o alvo definido.">
        <div className="grid gap-4 lg:grid-cols-2">
          {(data.items || []).map((goal) => (
            <article key={goal.id} className="rounded-[28px] border border-slate-200 bg-white p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-slate-950">{goal.title}</h3>
                  <p className="mt-1 text-sm text-slate-500">
                    {goal.target_date ? `Meta para ${goal.target_date}` : 'Sem data limite definida'}
                  </p>
                </div>
                <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                  {goal.status}
                </span>
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div className="rounded-2xl bg-slate-50 px-4 py-3">
                  <p className="text-xs uppercase tracking-[0.16em] text-slate-500">Valor atual</p>
                  <p className="mt-2 text-xl font-bold text-slate-950">{formatCurrency(goal.current_amount)}</p>
                </div>
                <div className="rounded-2xl bg-slate-50 px-4 py-3">
                  <p className="text-xs uppercase tracking-[0.16em] text-slate-500">Valor alvo</p>
                  <p className="mt-2 text-xl font-bold text-slate-950">{formatCurrency(goal.target_amount)}</p>
                </div>
              </div>

              <div className="mt-4">
                <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
                  <span>Progresso</span>
                  <strong className="text-slate-950">{formatPercent(goal.progress_percent)}</strong>
                </div>
                <div className="mt-2 h-3 rounded-full bg-slate-100">
                  <div
                    className="h-3 rounded-full bg-emerald-600"
                    style={{ width: `${Math.min(Number(goal.progress_percent || 0), 100)}%` }}
                  />
                </div>
              </div>

              <div className="mt-5 flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(goal)}>
                  <Pencil className="h-4 w-4" />
                  Editar
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => handleDelete(goal.id)}>
                  <Trash2 className="h-4 w-4" />
                  Excluir
                </Button>
              </div>
            </article>
          ))}

          {(data.items || []).length === 0 ? (
            <div className="muted-line lg:col-span-2">Nenhuma meta cadastrada ainda.</div>
          ) : null}
        </div>
      </SectionCard>
    </div>
  );
}
