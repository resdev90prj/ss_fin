import { useEffect, useState } from 'react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber } from '../../lib/formatters';

export default function CategoriesPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();

    async function loadCategories() {
      setLoading(true);
      setError('');

      try {
        const response = await apiRequest('/categories', { signal: controller.signal });
        setData(response.data);
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Nao foi possivel carregar as categorias.');
        }
      } finally {
        setLoading(false);
      }
    }

    loadCategories();
    return () => controller.abort();
  }, []);

  if (loading) {
    return <LoadingState text="Lendo o catalogo de categorias do sistema atual." />;
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
          <h1>Template padrao + categorias por usuario</h1>
          <p>O React le o mesmo conjunto que o legado usa para formularios, filtros e classificacao automatica.</p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Total" value={formatNumber(summary.total)} />
        <StatCard label="Padrao do sistema" value={formatNumber(summary.default_count)} tone="accent" />
        <StatCard label="Personalizadas" value={formatNumber(summary.custom_count)} tone="neutral" />
        <StatCard label="Ativas" value={formatNumber(summary.active_count)} tone="positive" />
      </div>

      <SectionCard title="Lista de categorias" subtitle="Base atual protegida por user_id.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Origem</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((category) => (
                <tr key={category.id}>
                  <td>{category.name}</td>
                  <td>{category.type}</td>
                  <td>{category.is_default ? 'Padrao' : 'Customizada'}</td>
                  <td>{category.status === 'active' ? 'Ativa' : 'Inativa'}</td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="4" className="empty-cell">Nenhuma categoria encontrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
