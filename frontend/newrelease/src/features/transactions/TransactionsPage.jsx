import { useEffect, useRef, useState } from 'react';
import { Pencil, Sparkles, Trash2, X } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatDate, formatNumber } from '../../lib/formatters';

const initialFilters = {
  from: '',
  to: '',
  type: '',
  category_id: '',
  account_id: '',
  prioritize_others: true,
};

const emptyForm = {
  description: '',
  amount: '',
  transaction_date: new Date().toISOString().slice(0, 10),
  type: 'expense',
  mode: 'transicao',
  category_id: '',
  account_id: '',
  box_id: '',
  payment_method: '',
  notes: '',
};

export default function TransactionsPage() {
  const { session } = useAuth();
  const [filters, setFilters] = useState(initialFilters);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [suggestionText, setSuggestionText] = useState('');
  const [suggestionTone, setSuggestionTone] = useState('text-slate-500');
  const categoryTouchedRef = useRef(false);

  async function loadTransactions(nextFilters = filters, signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/transactions', {
        data: {
          ...nextFilters,
          prioritize_others: nextFilters.prioritize_others ? 1 : 0,
        },
        signal,
      });

      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as transacoes.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadTransactions(filters, controller.signal);
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (form.description.trim().length < 3) {
      setSuggestionText('');
      setSuggestionTone('text-slate-500');
      return undefined;
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(async () => {
      try {
        const response = await apiRequest('/transactions/suggest-category', {
          data: {
            description: form.description,
            type: form.type,
          },
          signal: controller.signal,
        });

        const suggestion = response.data?.suggestion;
        if (!suggestion) {
          setSuggestionText('');
          setSuggestionTone('text-slate-500');
          return;
        }

        const categoryName = suggestion.category_name || 'categoria sugerida';
        if (suggestion.confidence === 'high') {
          if (!categoryTouchedRef.current || !form.category_id) {
            setForm((current) => ({
              ...current,
              category_id: String(suggestion.category_id || ''),
            }));
          }
          setSuggestionText(`Categoria sugerida com alta confianca: ${categoryName}.`);
          setSuggestionTone('text-emerald-700');
          return;
        }

        if (suggestion.confidence === 'medium') {
          setSuggestionText(`Sugestao com media confianca: ${categoryName}. Confirme antes de salvar.`);
          setSuggestionTone('text-amber-700');
          return;
        }

        setSuggestionText('Sem confianca suficiente para sugerir categoria automaticamente.');
        setSuggestionTone('text-slate-500');
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setSuggestionText('');
          setSuggestionTone('text-slate-500');
        }
      }
    }, 350);

    return () => {
      controller.abort();
      window.clearTimeout(timeoutId);
    };
  }, [form.description, form.type, form.category_id]);

  function resetForm() {
    setForm(emptyForm);
    setEditingId(null);
    setSuggestionText('');
    categoryTouchedRef.current = false;
  }

  function handleFilterChange(event) {
    const { name, value, type, checked } = event.target;
    setFilters((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  }

  function handleFormChange(event) {
    const { name, value } = event.target;
    if (name === 'category_id') {
      categoryTouchedRef.current = true;
    }

    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  async function handleFilterSubmit(event) {
    event.preventDefault();
    await loadTransactions(filters);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      await apiRequest(editingId ? '/transactions/update' : '/transactions', {
        method: 'POST',
        data: {
          ...form,
          id: editingId,
          amount: Number(form.amount || 0),
          account_id: form.account_id ? Number(form.account_id) : 0,
          category_id: form.category_id ? Number(form.category_id) : 0,
          box_id: form.box_id ? Number(form.box_id) : null,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(editingId ? 'Transacao atualizada com sucesso.' : 'Transacao cadastrada com sucesso.');
      resetForm();
      await loadTransactions(filters);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar a transacao.');
    } finally {
      setSubmitting(false);
    }
  }

  function handleEdit(transaction) {
    setEditingId(transaction.id);
    setForm({
      description: transaction.description || '',
      amount: String(transaction.amount ?? ''),
      transaction_date: transaction.transaction_date || new Date().toISOString().slice(0, 10),
      type: transaction.type || 'expense',
      mode: transaction.mode || 'transicao',
      category_id: transaction.category_id ? String(transaction.category_id) : '',
      account_id: transaction.account_id ? String(transaction.account_id) : '',
      box_id: transaction.box_id ? String(transaction.box_id) : '',
      payment_method: transaction.payment_method || '',
      notes: transaction.notes || '',
    });
    categoryTouchedRef.current = true;
    setError('');
    setNotice('');
  }

  async function handleDelete(transactionId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/transactions/delete', {
        method: 'POST',
        data: {
          id: transactionId,
          csrf_token: session.csrf_token,
        },
      });

      if (editingId === transactionId) {
        resetForm();
      }

      setNotice('Transacao excluida com sucesso.');
      await loadTransactions(filters);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel excluir a transacao.');
    }
  }

  async function handleAutoClassify() {
    setError('');
    setNotice('');

    try {
      const response = await apiRequest('/transactions/auto-classify-others', {
        method: 'POST',
        data: {
          csrf_token: session.csrf_token,
        },
      });

      const result = response.data?.result || {};
      setNotice(
        `Reclassificacao concluida: ${formatNumber(result.reclassified)} atualizadas, ` +
        `${formatNumber(result.remaining_others)} ainda em "Outros gastos".`,
      );
      await loadTransactions(filters);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel executar a classificacao em lote.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Montando o fluxo operacional de receitas, despesas, retiradas e transferencias." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Transacoes indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as transacoes.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const lookups = data.lookups || {};

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Transacoes</span>
          <h1>Controle receitas, despesas, retiradas e transferencias em um unico fluxo.</h1>
          <p>
            Cadastre lancamentos, aplique filtros e trate classificacoes pendentes no mesmo lugar.
          </p>
        </div>
      </section>

      <form className="filter-card" onSubmit={handleFilterSubmit}>
        <label>
          <span>De</span>
          <input type="date" name="from" value={filters.from} onChange={handleFilterChange} />
        </label>

        <label>
          <span>Ate</span>
          <input type="date" name="to" value={filters.to} onChange={handleFilterChange} />
        </label>

        <label>
          <span>Tipo</span>
          <select name="type" value={filters.type} onChange={handleFilterChange}>
            <option value="">Todos</option>
            <option value="income">Receita</option>
            <option value="expense">Despesa</option>
            <option value="partner_withdrawal">Retirada</option>
            <option value="transfer">Transferencia</option>
          </select>
        </label>

        <label>
          <span>Conta</span>
          <select name="account_id" value={filters.account_id} onChange={handleFilterChange}>
            <option value="">Todas</option>
            {(lookups.accounts || []).map((account) => (
              <option key={account.id} value={account.id}>
                {account.name}
              </option>
            ))}
          </select>
        </label>

        <label>
          <span>Categoria</span>
          <select name="category_id" value={filters.category_id} onChange={handleFilterChange}>
            <option value="">Todas</option>
            {(lookups.categories || []).map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </label>

        <label className="checkbox-line">
          <input
            type="checkbox"
            name="prioritize_others"
            checked={filters.prioritize_others}
            onChange={handleFilterChange}
          />
          <span>Priorizar "Outros gastos"</span>
        </label>

        <div className="flex flex-wrap items-end gap-3 xl:col-span-2">
          <Button type="submit">Aplicar filtros</Button>
          <Button type="button" variant="outline" onClick={handleAutoClassify}>
            <Sparkles className="h-4 w-4" />
            Reclassificar "Outros gastos"
          </Button>
        </div>
      </form>

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Lancamentos" value={formatNumber(summary.total)} />
        <StatCard label="Receitas" value={formatCurrency(summary.income_total)} tone="positive" />
        <StatCard label="Despesas" value={formatCurrency(summary.expense_total)} tone="danger" />
        <StatCard label="Retiradas" value={formatCurrency(summary.withdrawal_total)} tone="warning" />
        <StatCard label="Transferencias" value={formatCurrency(summary.transfer_total)} tone="accent" />
        <StatCard label='Pendentes em "Outros gastos"' value={formatNumber(summary.others_pending_count)} tone="neutral" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard
        title={editingId ? 'Editar lancamento' : 'Novo lancamento'}
        subtitle="Descreva o lancamento e revise a sugestao de categoria antes de salvar."
      >
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Descricao</span>
            <input name="description" value={form.description} onChange={handleFormChange} placeholder="Ex.: Pagamento de fornecedor" required />
          </label>

          <label>
            <span>Valor</span>
            <input name="amount" type="number" step="0.01" value={form.amount} onChange={handleFormChange} required />
          </label>

          <label>
            <span>Data</span>
            <input name="transaction_date" type="date" value={form.transaction_date} onChange={handleFormChange} required />
          </label>

          <label>
            <span>Tipo</span>
            <select name="type" value={form.type} onChange={handleFormChange}>
              <option value="income">Receita</option>
              <option value="expense">Despesa</option>
              <option value="partner_withdrawal">Retirada</option>
              <option value="transfer">Transferencia</option>
            </select>
          </label>

          <label>
            <span>Modo</span>
            <select name="mode" value={form.mode} onChange={handleFormChange}>
              <option value="transitorio">Transitorio</option>
              <option value="transicao">Transicao</option>
              <option value="ideal">Ideal</option>
            </select>
          </label>

          <label>
            <span>Categoria</span>
            <select name="category_id" value={form.category_id} onChange={handleFormChange} required>
              <option value="">Selecione</option>
              {(lookups.categories || []).map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Conta</span>
            <select name="account_id" value={form.account_id} onChange={handleFormChange} required>
              <option value="">Selecione</option>
              {(lookups.accounts || []).map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Caixa</span>
            <select name="box_id" value={form.box_id} onChange={handleFormChange}>
              <option value="">Sem caixa</option>
              {(lookups.boxes || []).map((box) => (
                <option key={box.id} value={box.id}>
                  {box.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Forma de pagamento</span>
            <input name="payment_method" value={form.payment_method} onChange={handleFormChange} placeholder="Pix, boleto, cartao..." />
          </label>

          <label className="xl:col-span-2">
            <span>Observacoes</span>
            <input name="notes" value={form.notes} onChange={handleFormChange} placeholder="Complementos opcionais" />
          </label>

          <div className="xl:col-span-4">
            {suggestionText ? (
              <p className={`text-sm font-medium ${suggestionTone}`}>
                {suggestionText}
              </p>
            ) : null}
          </div>

          <div className="flex flex-wrap gap-3 xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Cadastrar transacao'}
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

      <SectionCard title="Grid operacional" subtitle="Lista completa com destaque visual para itens ainda em classificacao pendente.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Descricao</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Categoria</th>
                <th>Conta</th>
                <th>Data</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((transaction) => (
                <tr key={transaction.id} className={transaction.is_others_category ? 'row-highlight' : ''}>
                  <td>
                    <strong>{transaction.description}</strong>
                    <small>{transaction.payment_method || 'Sem forma de pagamento'}</small>
                  </td>
                  <td>{transaction.type}</td>
                  <td>{formatCurrency(transaction.amount)}</td>
                  <td>{transaction.category_name}</td>
                  <td>{transaction.account_name}</td>
                  <td>{formatDate(transaction.transaction_date)}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => handleEdit(transaction)}>
                        <Pencil className="h-4 w-4" />
                        Editar
                      </Button>
                      <Button type="button" size="sm" variant="ghost" onClick={() => handleDelete(transaction.id)}>
                        <Trash2 className="h-4 w-4" />
                        Excluir
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="7" className="empty-cell">Nenhuma transacao encontrada para os filtros atuais.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>
    </div>
  );
}
