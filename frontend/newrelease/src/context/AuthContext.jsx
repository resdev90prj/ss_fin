import { createContext, useContext, useEffect, useState } from 'react';
import { apiRequest } from '../lib/apiClient';

const emptySession = {
  authenticated: false,
  csrf_token: '',
  user: null,
  scope: {
    logged_user_id: null,
    current_user_id: null,
    scoped_user_id: null,
    is_admin: false,
  },
  release: {
    app_name: 'SaaS IA Finan',
    react_base: '/newrelease',
    api_base: '/api',
  },
};

const AuthContext = createContext({
  session: emptySession,
  loading: true,
  authError: '',
  refreshSession: async () => {},
  login: async () => {},
  logout: async () => {},
});

export function AuthProvider({ children }) {
  const [session, setSession] = useState(emptySession);
  const [loading, setLoading] = useState(true);
  const [authError, setAuthError] = useState('');

  async function refreshSession(signal) {
    const response = await apiRequest('/me', { signal });
    setSession({
      ...emptySession,
      ...response.data,
      scope: {
        ...emptySession.scope,
        ...(response.data?.scope || {}),
      },
      release: {
        ...emptySession.release,
        ...(response.data?.release || {}),
      },
    });
    setAuthError('');
    return response.data;
  }

  async function login({ email, password }) {
    const csrfToken = session.csrf_token || (await refreshSession()).csrf_token;
    const response = await apiRequest('/login', {
      method: 'POST',
      data: {
        email,
        password,
        csrf_token: csrfToken,
      },
    });

    setSession({
      ...emptySession,
      ...response.data,
      scope: {
        ...emptySession.scope,
        ...(response.data?.scope || {}),
      },
      release: {
        ...emptySession.release,
        ...(response.data?.release || {}),
      },
    });
    setAuthError('');
    return response.data;
  }

  async function logout() {
    try {
      const response = await apiRequest('/logout', {
        method: 'POST',
        data: {
          csrf_token: session.csrf_token,
        },
      });

      setSession({
        ...emptySession,
        ...response.data,
        scope: {
          ...emptySession.scope,
          ...(response.data?.scope || {}),
        },
        release: {
          ...emptySession.release,
          ...(response.data?.release || {}),
        },
      });
    } catch (error) {
      setSession(emptySession);
    }
  }

  useEffect(() => {
    const controller = new AbortController();

    async function bootstrap() {
      setLoading(true);
      try {
        await refreshSession(controller.signal);
      } catch (error) {
        setAuthError(error.message || 'Nao foi possivel validar a sessao atual.');
        setSession(emptySession);
      } finally {
        setLoading(false);
      }
    }

    bootstrap();

    return () => controller.abort();
  }, []);

  return (
    <AuthContext.Provider
      value={{
        session,
        loading,
        authError,
        refreshSession,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
