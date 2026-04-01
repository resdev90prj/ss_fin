import { useEffect, useState } from 'react';
import { RotateCw, Upload } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';
import { formatNumber } from '../../lib/formatters';

export default function ImportsPage() {
  const { session } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [accountId, setAccountId] = useState('');
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [processing, setProcessing] = useState(false);

  async function loadImports(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/imports', { signal });
      setData(response.data);
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar o modulo de importacao.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadImports(controller.signal);
    return () => controller.abort();
  }, []);

  async function handleUpload(event) {
    event.preventDefault();
    if (!file) {
      setError('Selecione um arquivo para importar.');
      return;
    }

    setUploading(true);
    setError('');
    setNotice('');

    try {
      const formData = new FormData();
      formData.append('account_id', accountId);
      formData.append('csrf_token', session.csrf_token);
      formData.append('statement', file);

      const response = await apiRequest('/imports/upload', {
        method: 'POST',
        formData,
      });

      const summary = response.data?.summary || {};
      setNotice(
        `Importacao concluida: ${formatNumber(summary.inserted)} lancamentos, ` +
        `${formatNumber(summary.classified_high)} alta confianca, ` +
        `${formatNumber(summary.classified_medium)} media, ` +
        `${formatNumber(summary.fallback_used)} por categoria padrao.`,
      );
      setFile(null);
      setAccountId('');
      await loadImports();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel importar o arquivo.');
    } finally {
      setUploading(false);
    }
  }

  async function handleProcessQueue() {
    setProcessing(true);
    setError('');
    setNotice('');

    try {
      const response = await apiRequest('/imports/process-ofx-queue', {
        method: 'POST',
        data: {
          csrf_token: session.csrf_token,
        },
      });

      const summary = response.data?.summary || {};
      setNotice(
        `Fila OFX processada: ${formatNumber(summary.files_processed)} arquivos, ` +
        `${formatNumber(summary.transactions_created)} lancamentos criados.`,
      );
      await loadImports();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel processar a fila OFX.');
    } finally {
      setProcessing(false);
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando o painel de importacao manual e fila OFX automatizada." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Importacao indisponivel</h2>
        <p>{error || 'Nao foi possivel carregar o modulo de importacao.'}</p>
      </section>
    );
  }

  const accounts = data.accounts || [];
  const queueData = data.queue_data || {};
  const lastRun = queueData.last_run || {};
  const pendingFiles = queueData.pending_files || [];
  const processedFiles = queueData.processed_files || [];
  const errorFiles = queueData.error_files || [];
  const recentLogs = queueData.recent_logs || [];
  const recentErrors = queueData.recent_errors || [];

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Importacao</span>
          <h1>Importe extratos e acompanhe o processamento em um so lugar.</h1>
          <p>
            Envie arquivos, acompanhe o processamento e revise o historico recente das importacoes.
          </p>
        </div>
      </section>

      <div className="stats-grid stats-grid--wide">
        <StatCard label="Arquivos processados" value={formatNumber(lastRun.files_processed)} icon={<Upload className="h-5 w-5" />} />
        <StatCard label="Lancamentos criados" value={formatNumber(lastRun.transactions_created)} tone="positive" />
        <StatCard label="Duplicidades ignoradas" value={formatNumber(lastRun.transactions_ignored_duplicate)} tone="accent" />
        <StatCard label="Arquivos com falha" value={formatNumber(lastRun.files_failed)} tone="danger" />
        <StatCard label="Classificacao alta" value={formatNumber(lastRun.transactions_classified_high)} tone="positive" />
        <StatCard label="Categoria padrao" value={formatNumber(lastRun.transactions_fallback_used)} tone="warning" />
      </div>

      {(error || notice || data.queue_error) ? (
        <div className={`alert-banner ${(error || data.queue_error) ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || data.queue_error || notice}
        </div>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-2">
        <SectionCard title="Importacao manual" subtitle="Arquivos suportados: CSV, OFX e XLSX.">
          <form className="space-y-4" onSubmit={handleUpload}>
            <label>
              <span>Conta destino</span>
              <select value={accountId} onChange={(event) => setAccountId(event.target.value)} required>
                <option value="">Selecione</option>
                {accounts.map((account) => (
                  <option key={account.id} value={account.id}>
                    {account.name}
                  </option>
                ))}
              </select>
            </label>

            <label>
              <span>Arquivo</span>
              <input type="file" accept=".csv,.ofx,.xlsx" onChange={(event) => setFile(event.target.files?.[0] || null)} required />
            </label>

            <Button type="submit" disabled={uploading}>
              <Upload className="h-4 w-4" />
              {uploading ? 'Importando...' : 'Importar extrato'}
            </Button>
          </form>
        </SectionCard>

        <SectionCard title="Fila OFX automatizada" subtitle="Processe os arquivos aguardando importacao.">
          <div className="space-y-4">
            <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
              <p>Arquivos pendentes: <strong>{formatNumber(pendingFiles.length)}</strong></p>
              <p className="mt-2">Arquivos processados recentes: <strong>{formatNumber(processedFiles.length)}</strong></p>
              <p className="mt-2">Arquivos com falha recentes: <strong>{formatNumber(errorFiles.length)}</strong></p>
            </div>

            <Button type="button" variant="outline" onClick={handleProcessQueue} disabled={processing}>
              <RotateCw className="h-4 w-4" />
              {processing ? 'Processando...' : 'Processar fila OFX agora'}
            </Button>
          </div>
        </SectionCard>
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <SectionCard title="Arquivos pendentes" subtitle="Arquivos aguardando processamento na fila OFX.">
          <div className="stack-list">
            {pendingFiles.length === 0 ? <div className="muted-line">Sem arquivos pendentes.</div> : null}
            {pendingFiles.map((item) => (
              <article key={`${item.name}-${item.modified_at}`} className="stack-item">
                <div>
                  <strong>{item.name}</strong>
                  <p>{item.modified_at}</p>
                </div>
                <small>{formatNumber(Math.round((item.size || 0) / 1024))} KB</small>
              </article>
            ))}
          </div>
        </SectionCard>

        <SectionCard title="Erros recentes" subtitle="Ultimas ocorrencias registradas pela fila OFX.">
          <div className="stack-list">
            {recentErrors.length === 0 ? <div className="muted-line">Sem erros recentes.</div> : null}
            {recentErrors.map((line) => (
              <article key={line} className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">
                {line}
              </article>
            ))}
          </div>
        </SectionCard>
      </div>

      <SectionCard title="Historico recente" subtitle="Acompanhe as ultimas execucoes de processamento.">
        <div className="stack-list">
          {recentLogs.length === 0 ? <div className="muted-line">Sem logs disponiveis ainda.</div> : null}
          {recentLogs.map((line) => (
            <article key={line} className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
              {line}
            </article>
          ))}
        </div>
      </SectionCard>
    </div>
  );
}
