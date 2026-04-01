import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

function resolveRedirectTarget(location) {
  const target = location.state?.from;

  if (typeof target !== 'string' || target === '' || target === '/login' || target === '/logout') {
    return '/';
  }

  return target;
}

export default function LoginPage() {
  const { session, login, authError } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const redirectTarget = resolveRedirectTarget(location);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  if (session.authenticated) {
    return <Navigate to={redirectTarget} replace />;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setBusy(true);
    setError('');

    try {
      await login({ email, password });
      navigate(redirectTarget, { replace: true });
    } catch (requestError) {
      const errors = Array.isArray(requestError.errors) && requestError.errors.length > 0
        ? requestError.errors.join(' ')
        : requestError.message;
      setError(errors || 'Nao foi possivel autenticar.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-layout">
      <section className="login-panel login-panel--highlight">
        <div>
          <span className="login-kicker">SaaS IA Finan</span>
          <h1>Entre para acompanhar seu financeiro com clareza e seguranca.</h1>
          <p>
            Reuna saldos, metas, agenda e rotina financeira em um so lugar.
          </p>

          <div className="feature-stack">
            <article>
              <strong>Visao do dia</strong>
              <p>Tenha uma leitura rapida do caixa, das prioridades e dos compromissos da competencia.</p>
            </article>
            <article>
              <strong>Controle financeiro</strong>
              <p>Organize contas, categorias, transacoes, retiradas, dividas e importacoes com fluidez.</p>
            </article>
            <article>
              <strong>Planejamento em acao</strong>
              <p>Acompanhe metas, agenda e proximas acoes para manter a execucao em dia.</p>
            </article>
          </div>
        </div>
      </section>

      <section className="login-panel">
        <div className="login-card">
          <span className="login-kicker">Acesso seguro</span>
          <h2>Entrar</h2>
          <p>Use seu e-mail e senha para continuar.</p>

          {(error || authError) && (
            <div className="alert-banner alert-banner--danger">
              {error || authError}
            </div>
          )}

          <form className="login-form" onSubmit={handleSubmit}>
            <label>
              <span>E-mail</span>
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="voce@empresa.com"
                autoComplete="username"
                required
              />
            </label>

            <label>
              <span>Senha</span>
              <input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Sua senha atual"
                autoComplete="current-password"
                required
              />
            </label>

            <button className="solid-button solid-button--wide" type="submit" disabled={busy}>
              {busy ? 'Validando acesso...' : 'Entrar'}
            </button>
          </form>
        </div>
      </section>
    </div>
  );
}
