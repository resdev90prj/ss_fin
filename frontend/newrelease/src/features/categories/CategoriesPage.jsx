import { useEffect, useState } from 'react';
import { Pencil, Trash2, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber } from '../../lib/formatters';

const emptyForm = {
  name: '',
  type: 'expense',
  status: 'active',
};

export default function CategoriesPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  async function loadCategories(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/categories', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as categorias.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadCategories(controller.signal);
    return () => controller.abort();
  }, []);

  function resetForm() {
    setForm(emptyForm);
    setEditingId(null);
  }

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleEdit(category) {
    setEditingId(category.id);
    setForm({
      name: category.name || '',
      type: category.type || 'expense',
      status: category.status || 'active',
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
      await apiRequest(editingId ? '/categories/update' : '/categories', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Categoria atualizada com sucesso.' : 'Categoria criada com sucesso.');
      resetForm();
      await loadCategories();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar a categoria.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(categoryId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/categories/delete', {
        method: 'POST',
        data: {
          id: categoryId,
          csrf_token: session.csrf_token,
        },
      });

      if (editingId === categoryId) {
        resetForm();
      }

      setNotice('Categoria removida com sucesso.');
      await loadCategories();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel excluir a categoria.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando as categorias que alimentam filtros, cadastros e classificacao." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Categorias indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as categorias.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Categorias</span>
          <h1>Organize a leitura do seu financeiro com uma base consistente e flexivel.</h1>
          <p>
            Crie categorias personalizadas e mantenha sua estrutura financeira organizada no dia a dia.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Total" value={formatNumber(summary.total)} />
        <StatCard label="Padrao do sistema" value={formatNumber(summary.default_count)} tone="accent" />
        <StatCard label="Personalizadas" value={formatNumber(summary.custom_count)} tone="neutral" />
        <StatCard label="Ativas" value={formatNumber(summary.active_count)} tone="positive" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard
        title={editingId ? 'Editar categoria' : 'Nova categoria'}
        subtitle="Cadastre categorias para receitas, despesas ou uso misto."
      >
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Nome</span>
            <input name="name" value={form.name} onChange={handleChange} placeholder="Ex.: Marketing" required />
          </label>

          <label>
            <span>Tipo</span>
            <select name="type" value={form.type} onChange={handleChange}>
              <option value="income">Receita</option>
              <option value="expense">Despesa</option>
              <option value="both">Ambos</option>
            </select>
          </label>

          <label>
            <span>Status</span>
            <select name="status" value={form.status} onChange={handleChange}>
              <option value="active">Ativa</option>
              <option value="inactive">Inativa</option>
            </select>
          </label>

          <div className="flex items-end gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Criar categoria'}
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

      <SectionCard title="Lista de categorias" subtitle="Base pronta para alimentar transacoes, orcamentos, metas e importacoes.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Origem</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((category) => (
                <tr key={category.id}>
                  <td>
                    <strong>{category.name}</strong>
                    <small>ID {category.id}</small>
                  </td>
                  <td>{category.type}</td>
                  <td>{category.is_default ? 'Padrao' : 'Customizada'}</td>
                  <td>{category.status === 'active' ? 'Ativa' : 'Inativa'}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(category)}>
                        <Pencil className="h-4 w-4" />
                        Editar
                      </Button>
                      {!category.is_default ? (
                        <Button type="button" size="sm" variant="ghost" onClick={() => handleDelete(category.id)}>
                          <Trash2 className="h-4 w-4" />
                          Excluir
                        </Button>
                      ) : (
                        <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                          Protegida
                        </span>
                      )}
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="5" className="empty-cell">Nenhuma categoria encontrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
