import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function LoginPage() {
  const { session, login, authError } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  if (session.authenticated) {
    return <Navigate to={location.state?.from || '/'} replace />;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setBusy(true);
    setError('');

    try {
      await login({ email, password });
      navigate(location.state?.from || '/', { replace: true });
    } catch (requestError) {
      const errors = Array.isArray(requestError.errors) && requestError.errors.length > 0
        ? requestError.errors.join(' ')
        : requestError.message;
      setError(errors || 'Nao foi possivel autenticar na nova release.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-layout">
      <section className="login-panel login-panel--highlight">
        <div>
          <span className="login-kicker">New release / React pilot</span>
          <h1>Validacao segura da nova interface sem trocar o legado</h1>
          <p>
            Esta release usa a mesma sessao PHP, a mesma seguranca por user_id e os
            mesmos models do sistema atual. O frontend mudou; as regras criticas nao.
          </p>

          <div className="feature-stack">
            <article>
              <strong>Rota isolada</strong>
              <p>O piloto roda em /newrelease sem tocar o fluxo principal em /.</p>
            </article>
            <article>
              <strong>API em PHP</strong>
              <p>Os endpoints JSON reaproveitam autenticacao, permissao e sessao do sistema atual.</p>
            </article>
            <article>
              <strong>Rollback simples</strong>
              <p>Se houver qualquer problema, basta manter os usuarios no legado enquanto a nova release evolui.</p>
            </article>
          </div>
        </div>
      </section>

      <section className="login-panel">
        <div className="login-card">
          <span className="login-kicker">Acesso controlado</span>
          <h2>Entrar na nova release</h2>
          <p>Use suas credenciais atuais. A autenticacao continua centralizada no PHP.</p>

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
              {busy ? 'Validando acesso...' : 'Entrar no piloto React'}
            </button>
          </form>
        </div>
      </section>
    </div>
  );
}

