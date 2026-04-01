import { useEffect, useState } from 'react';
import { BellRing, KeyRound, UserRound } from 'lucide-react';
import LoadingState from '../../components/LoadingState';
import SectionCard from '../../components/SectionCard';
import StatCard from '../../components/StatCard';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../lib/apiClient';

const emptyPasswordForm = {
  current_password: '',
  new_password: '',
  confirm_password: '',
};

export default function ProfilePage() {
  const { session, refreshSession } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [profileForm, setProfileForm] = useState({ name: '', email: '' });
  const [passwordForm, setPasswordForm] = useState(emptyPasswordForm);
  const [alertsForm, setAlertsForm] = useState({
    receber_alerta_email: false,
    email_notificacao: '',
    alerta_frequencia: 'daily',
    alerta_horario: '08:00',
  });
  const [saving, setSaving] = useState('');

  async function loadProfile(signal) {
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest('/profile', { signal });
      const payload = response.data;
      setData(payload);
      setProfileForm({
        name: payload.user?.name || '',
        email: payload.user?.email || '',
      });
      setAlertsForm({
        receber_alerta_email: Boolean(payload.alert_preferences?.receber_alerta_email),
        email_notificacao: payload.alert_preferences?.email_notificacao || '',
        alerta_frequencia: payload.alert_preferences?.alerta_frequencia || 'daily',
        alerta_horario: payload.alert_preferences?.alerta_horario || '08:00',
      });
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setError(requestError.message || 'Nao foi possivel carregar seu perfil.');
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    loadProfile(controller.signal);
    return () => controller.abort();
  }, []);

  function handleProfileChange(event) {
    const { name, value } = event.target;
    setProfileForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handlePasswordChange(event) {
    const { name, value } = event.target;
    setPasswordForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function handleAlertsChange(event) {
    const { name, value, type, checked } = event.target;
    setAlertsForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  }

  async function submitProfile(event) {
    event.preventDefault();
    setSaving('profile');
    setError('');
    setNotice('');

    try {
      await apiRequest('/profile/update', {
        method: 'POST',
        data: {
          ...profileForm,
          csrf_token: session.csrf_token,
        },
      });
      await refreshSession();
      setNotice('Dados atualizados com sucesso.');
      await loadProfile();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel atualizar seus dados.');
    } finally {
      setSaving('');
    }
  }

  async function submitPassword(event) {
    event.preventDefault();
    setSaving('password');
    setError('');
    setNotice('');

    try {
      await apiRequest('/profile/password', {
        method: 'POST',
        data: {
          ...passwordForm,
          csrf_token: session.csrf_token,
        },
      });
      setNotice('Senha alterada com sucesso.');
      setPasswordForm(emptyPasswordForm);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel alterar sua senha.');
    } finally {
      setSaving('');
    }
  }

  async function submitAlerts(event) {
    event.preventDefault();
    setSaving('alerts');
    setError('');
    setNotice('');

    try {
      await apiRequest('/profile/alerts', {
        method: 'POST',
        data: {
          ...alertsForm,
          csrf_token: session.csrf_token,
        },
      });
      setNotice('Preferencias de alerta salvas com sucesso.');
      await loadProfile();
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel salvar as preferencias de alerta.');
    } finally {
      setSaving('');
    }
  }

  if (loading && !data) {
    return <LoadingState text="Carregando seus dados de acesso e preferencias de notificacao." />;
  }

  if (!data) {
    return (
      <section className="state-card">
        <h2>Meu acesso indisponivel</h2>
        <p>{error || 'Nao foi possivel carregar seus dados de acesso.'}</p>
      </section>
    );
  }

  const user = data.user || {};
  const alertTableAvailable = Boolean(data.alert_preference_table_available);

  return (
    <div className="page-stack">
      <section className="hero-card">
        <div>
          <span className="hero-card__eyebrow">Meu acesso</span>
          <h1>Gerencie seus dados, sua senha e sua central de alertas em um so lugar.</h1>
          <p>
            Atualize seus dados, mantenha a senha em dia e ajuste como deseja receber alertas.
          </p>
        </div>
      </section>

      <div className="stats-grid">
        <StatCard label="Usuario" value={user.name || '-'} icon={<UserRound className="h-5 w-5" />} />
        <StatCard label="Perfil" value={user.role || 'user'} tone="accent" />
        <StatCard label="Status" value={Number(user.status) === 1 ? 'ativo' : 'inativo'} tone={Number(user.status) === 1 ? 'positive' : 'danger'} />
      </div>

      {(error || notice) ? (
        <div className={`alert-banner ${error ? 'alert-banner--danger' : 'alert-banner--success'}`}>
          {error || notice}
        </div>
      ) : null}

      {!alertTableAvailable ? (
        <div className="muted-line">
          Preferencias de alerta indisponiveis no momento.
        </div>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-3">
        <SectionCard title="Dados basicos" subtitle="Atualize nome e e-mail usados na sua conta.">
          <form className="space-y-4" onSubmit={submitProfile}>
            <label>
              <span>Nome</span>
              <input name="name" value={profileForm.name} onChange={handleProfileChange} required />
            </label>
            <label>
              <span>E-mail</span>
              <input name="email" type="email" value={profileForm.email} onChange={handleProfileChange} required />
            </label>
            <Button type="submit" disabled={saving === 'profile'}>
              {saving === 'profile' ? 'Salvando...' : 'Salvar dados'}
            </Button>
          </form>
        </SectionCard>

        <SectionCard title="Alterar senha" subtitle="Confirme a senha atual antes de definir uma nova.">
          <form className="space-y-4" onSubmit={submitPassword}>
            <label>
              <span>Senha atual</span>
              <input name="current_password" type="password" value={passwordForm.current_password} onChange={handlePasswordChange} minLength="6" required />
            </label>
            <label>
              <span>Nova senha</span>
              <input name="new_password" type="password" value={passwordForm.new_password} onChange={handlePasswordChange} minLength="6" required />
            </label>
            <label>
              <span>Confirmar nova senha</span>
              <input name="confirm_password" type="password" value={passwordForm.confirm_password} onChange={handlePasswordChange} minLength="6" required />
            </label>
            <Button type="submit" disabled={saving === 'password'}>
              <KeyRound className="h-4 w-4" />
              {saving === 'password' ? 'Salvando...' : 'Atualizar senha'}
            </Button>
          </form>
        </SectionCard>

        <SectionCard title="Central de alertas" subtitle="Defina o canal, a frequencia e o horario preferido para os alertas.">
          <form className="space-y-4" onSubmit={submitAlerts}>
            <label className="checkbox-line">
              <input
                type="checkbox"
                name="receber_alerta_email"
                checked={alertsForm.receber_alerta_email}
                onChange={handleAlertsChange}
              />
              <span>Receber alertas por e-mail</span>
            </label>

            <label>
              <span>E-mail de notificacao</span>
              <input name="email_notificacao" type="email" value={alertsForm.email_notificacao} onChange={handleAlertsChange} placeholder="Padrao: e-mail do login" />
            </label>

            <label>
              <span>Frequencia</span>
              <select name="alerta_frequencia" value={alertsForm.alerta_frequencia} onChange={handleAlertsChange}>
                <option value="daily">Diaria</option>
                <option value="weekdays">Dias uteis</option>
                <option value="manual">Manual</option>
              </select>
            </label>

            <label>
              <span>Horario preferido</span>
              <input name="alerta_horario" type="time" value={alertsForm.alerta_horario} onChange={handleAlertsChange} />
            </label>

            <Button type="submit" disabled={saving === 'alerts'}>
              <BellRing className="h-4 w-4" />
              {saving === 'alerts' ? 'Salvando...' : 'Salvar preferencias'}
            </Button>
          </form>
        </SectionCard>
      </div>
    </div>
  );
}
