import { useEffect, useState } from 'react';
import { Landmark, Trash2 } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatCurrency, formatDate, formatNumber } from '../../lib/formatters';

const emptyForm = {
  description: '',
  creditor: '',
  total_amount: '',
  start_date: new Date().toISOString().slice(0, 10),
  due_day: '',
  installments_count: '1',
  account_id: '',
  interest_mode: 'percent',
  interest_value: '0',
  penalty_mode: 'percent',
  penalty_value: '0',
  notes: '',
};

export default function DebtsPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [details, setDetails] = useState(null);
  const [selectedDebtId, setSelectedDebtId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [detailsLoading, setDetailsLoading] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [submitting, setSubmitting] = useState(false);
  const [paymentValues, setPaymentValues] = useState({});
  const [refundValues, setRefundValues] = useState({});

  async function loadDebts(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/debts', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar as dividas.');
      }
    } finally {
      setLoading(false);
    }
  }

  async function loadDetails(debtId, signal) {
    setDetailsLoading(true);
    setError('');

    try {
      const response = await apiRequest('/debts/details', {
        data: { id: debtId },
        signal,
      });
      setDetails(response.data);
      setSelectedDebtId(debtId);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar os detalhes da divida.');
      }
    } finally {
      setDetailsLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadDebts(controller.signal);
    return () => controller.abort();
  }, []);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setNotice('');

    try {
      const response = await apiRequest('/debts', {
        method: 'POST',
        data: {
          ...form,
          total_amount: Number(form.total_amount || 0),
          installments_count: Number(form.installments_count || 1),
          account_id: form.account_id ? Number(form.account_id) : null,
          interest_value: form.interest_value,
          penalty_value: form.penalty_value,
          csrf_token: session.csrf_token,
        },
      });

      setNotice('Divida cadastrada com sucesso.');
      setForm(emptyForm);
      await loadDebts();
      if (response.data?.debt_id) {
        await loadDetails(response.data.debt_id);
      }
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel cadastrar a divida.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleSelectDebt(debtId) {
    await loadDetails(debtId);
  }

  async function handleDeleteDebt(debtId) {
    setError('');
    setNotice('');

    try {
      await apiRequest('/debts/delete', {
        method: 'POST',
        data: {
          id: debtId,
          csrf_token: session.csrf_token,
        },
      });
      setNotice('Divida excluida com sucesso.');
      if (selectedDebtId === debtId) {
        setSelectedDebtId(null);
        setDetails(null);
      }
      await loadDebts();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel excluir a divida.');
    }
  }

  async function handleInstallmentAction(path, payload, successMessage) {
    setError('');
    setNotice('');

    try {
      const response = await apiRequest(path, {
        method: 'POST',
        data: {
          ...payload,
          csrf_token: session.csrf_token,
        },
      });

      setNotice(successMessage);
      await loadDebts();
      if (response.data?.debt_id || selectedDebtId) {
        await loadDetails(response.data?.debt_id || selectedDebtId);
      }
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel concluir a operacao na parcela.');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando as dividas, parcelas e regras de pagamento." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Dividas indisponiveis</h2>
        <p>{error || 'Nao foi possivel carregar as dividas.'}</p>
      </section>
    );
  }

  const summary = data.summary || {};
  const accounts = data.lookups?.accounts || [];

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Dividas</span>
          <h1>Cadastre dividas parceladas, acompanhe pagamentos e execute estornos sem sair do mesmo fluxo.</h1>
          <p>
            Acompanhe parcelas, pagamentos, estornos e saldo em aberto com mais clareza.
          </p>
        </div>
      </section>

      {!data.charges_enabled ? (
        <div className="muted-line">
          Juros e multa nao estao disponiveis no momento.
        </div>
      ) : null}

      <div className="stats-grid">
        <StatCard label="Dividas" value={formatNumber(summary.total)} icon={<Landmark className="h-5 w-5" />} />
        <StatCard label="Abertas" value={formatNumber(summary.open_count)} tone="warning" />
        <StatCard label="Quitadas" value={formatNumber(summary.paid_count)} tone="positive" />
        <StatCard label="Saldo em aberto" value={formatCurrency(summary.remaining_amount)} tone="danger" />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      <SectionCard title="Nova divida" subtitle="Informe valor, parcelas, vencimento e encargos para registrar a divida.">
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-6" onSubmit={handleSubmit}>
          <label className="xl:col-span-2">
            <span>Descricao</span>
            <input name="description" value={form.description} onChange={handleChange} placeholder="Ex.: Financiamento de equipamento" required />
          </label>

          <label>
            <span>Credor</span>
            <input name="creditor" value={form.creditor} onChange={handleChange} placeholder="Banco ou fornecedor" />
          </label>

          <label>
            <span>Valor total</span>
            <input name="total_amount" type="number" step="0.01" value={form.total_amount} onChange={handleChange} required />
          </label>

          <label>
            <span>Data inicial</span>
            <input name="start_date" type="date" value={form.start_date} onChange={handleChange} required />
          </label>

          <label>
            <span>Dia do vencimento</span>
            <input name="due_day" type="number" min="1" max="31" value={form.due_day} onChange={handleChange} />
          </label>

          <label>
            <span>Qtd. parcelas</span>
            <input name="installments_count" type="number" min="1" value={form.installments_count} onChange={handleChange} required />
          </label>

          <label>
            <span>Conta</span>
            <select name="account_id" value={form.account_id} onChange={handleChange}>
              <option value="">Sem conta</option>
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Juros</span>
            <select name="interest_mode" value={form.interest_mode} onChange={handleChange}>
              <option value="percent">%</option>
              <option value="fixed">R$</option>
            </select>
          </label>

          <label>
            <span>Valor juros</span>
            <input name="interest_value" value={form.interest_value} onChange={handleChange} />
          </label>

          <label>
            <span>Multa</span>
            <select name="penalty_mode" value={form.penalty_mode} onChange={handleChange}>
              <option value="percent">%</option>
              <option value="fixed">R$</option>
            </select>
          </label>

          <label>
            <span>Valor multa</span>
            <input name="penalty_value" value={form.penalty_value} onChange={handleChange} />
          </label>

          <label className="xl:col-span-3">
            <span>Observacoes</span>
            <input name="notes" value={form.notes} onChange={handleChange} placeholder="Anotacoes operacionais" />
          </label>

          <div className="flex items-end xl:col-span-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : 'Cadastrar divida'}
            </Button>
          </div>
        </form>
      </SectionCard>

      <SectionCard title="Carteira de dividas" subtitle="Selecione uma divida para abrir a visao detalhada de parcelas.">
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Descricao</th>
                <th>Credor</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Saldo</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              {(data.items || []).map((debt) => (
                <tr key={debt.id}>
                  <td>
                    <strong>{debt.description}</strong>
                    <small>{debt.account_name || 'Sem conta vinculada'}</small>
                  </td>
                  <td>{debt.creditor || '-'}</td>
                  <td>{formatCurrency(debt.total_amount)}</td>
                  <td>{formatCurrency(debt.paid_amount)}</td>
                  <td>{formatCurrency(debt.remaining)}</td>
                  <td>{debt.status}</td>
                  <td>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => handleSelectDebt(debt.id)}>
                        Ver parcelas
                      </Button>
                      {!debt.has_paid_installments ? (
                        <Button type="button" size="sm" variant="ghost" onClick={() => handleDeleteDebt(debt.id)}>
                          <Trash2 className="h-4 w-4" />
                          Excluir
                        </Button>
                      ) : (
                        <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                          Bloqueado
                        </span>
                      )}
                    </div>
                  </td>
                </tr>
              ))}

              {(data.items || []).length === 0 ? (
                <tr>
                  <td colSpan="7" className="empty-cell">Nenhuma divida cadastrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SectionCard>

      {selectedDebtId ? (
        <SectionCard
          title={details?.debt?.description ? `Parcelas de ${details.debt.description}` : 'Detalhes da divida'}
          subtitle="Acompanhe parcelas e registre pagamentos ou estornos com seguranca."
        >
          {detailsLoading ? (
            <LoadingState text="Carregando parcelas da divida selecionada." />
          ) : details ? (
            <div className="space-y-4">
              <div className="grid gap-4 md:grid-cols-3">
                <StatCard label="Total" value={formatCurrency(details.debt?.total_amount)} />
                <StatCard label="Pago" value={formatCurrency(details.debt?.paid_amount)} tone="positive" />
                <StatCard label="Saldo" value={formatCurrency((details.debt?.total_amount || 0) - (details.debt?.paid_amount || 0))} tone="danger" />
              </div>

              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Vencimento</th>
                      <th>Valor</th>
                      <th>Pago</th>
                      <th>Status</th>
                      <th>Acoes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(details.installments || []).map((installment) => (
                      <tr key={installment.id}>
                        <td>{installment.installment_number}</td>
                        <td>{formatDate(installment.due_date)}</td>
                        <td>{formatCurrency(installment.amount)}</td>
                        <td>{formatCurrency(installment.paid_amount)}</td>
                        <td>{installment.status}</td>
                        <td>
                          <div className="flex flex-col gap-2">
                            <div className="flex flex-wrap gap-2">
                              <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={paymentValues[installment.id] || ''}
                                onChange={(event) => setPaymentValues((current) => ({
                                  ...current,
                                  [installment.id]: event.target.value,
                                }))}
                                placeholder="Pagamento"
                              />
                              <Button
                                type="button"
                                size="sm"
                                onClick={() => handleInstallmentAction('/debts/pay-installment', {
                                  installment_id: installment.id,
                                  amount: paymentValues[installment.id] || 0,
                                }, 'Pagamento registrado com sucesso.')}
                              >
                                Pagar
                              </Button>
                            </div>

                            {Number(installment.paid_amount) > 0 ? (
                              <div className="flex flex-wrap gap-2">
                                <input
                                  type="number"
                                  step="0.01"
                                  min="0.01"
                                  value={refundValues[installment.id] || ''}
                                  onChange={(event) => setRefundValues((current) => ({
                                    ...current,
                                    [installment.id]: event.target.value,
                                  }))}
                                  placeholder="Estorno"
                                />
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleInstallmentAction('/debts/refund-installment', {
                                    installment_id: installment.id,
                                    amount: refundValues[installment.id] || 0,
                                  }, 'Estorno registrado com sucesso.')}
                                >
                                  Estornar
                                </Button>
                              </div>
                            ) : null}

                            {!installment.is_paid && Number(installment.paid_amount) === 0 ? (
                              <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => handleInstallmentAction('/debts/delete-installment', {
                                  installment_id: installment.id,
                                }, 'Parcela excluida com sucesso.')}
                              >
                                Excluir parcela
                              </Button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))}

                    {(details.installments || []).length === 0 ? (
                      <tr>
                        <td colSpan="6" className="empty-cell">Nenhuma parcela encontrada para esta divida.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="muted-line">Selecione uma divida para visualizar as parcelas.</div>
          )}
        </SectionCard>
      ) : null}
    </div>
  );
}
