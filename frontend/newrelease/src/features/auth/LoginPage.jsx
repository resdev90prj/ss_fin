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
      setError(errors || 'Nao foi possivel autenticar.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-layout">
      <section className="login-panel login-panel--highlight">
        <div>
          <span className="login-kicker">Workspace financeiro</span>
          <h1>Entre no seu workspace financeiro com a mesma seguranca do backend atual</h1>
          <p>
            Esta experiencia usa a mesma sessao PHP, o mesmo isolamento por user_id e
            as mesmas regras criticas do sistema. A interface mudou; a protecao permanece.
          </p>

          <div className="feature-stack">
            <article>
              <strong>Experiencia unificada</strong>
              <p>Os principais modulos operam dentro da mesma interface, sem depender de saltos para outra tela.</p>
            </article>
            <article>
              <strong>API em PHP</strong>
              <p>Os endpoints JSON reaproveitam autenticacao, permissao, sessao e regras de negocio do sistema atual.</p>
            </article>
            <article>
              <strong>Fluxo consistente</strong>
              <p>Dashboard, financeiro, operacao e conta compartilham a mesma navegacao e a mesma identidade visual.</p>
            </article>
          </div>
        </div>
      </section>

      <section className="login-panel">
        <div className="login-card">
          <span className="login-kicker">Acesso controlado</span>
          <h2>Entrar</h2>
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
              {busy ? 'Validando acesso...' : 'Entrar'}
            </button>
          </form>
        </div>
      </section>
    </div>
  );
}
