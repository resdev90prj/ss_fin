import SectionCard from './SectionCard';

export default function AlertsSummary({ executionCenter }) {
  const notifications = executionCenter?.notifications || [];
  const counts = executionCenter?.priority_counts || {
    critical: 0,
    high: 0,
    medium: 0,
    low: 0,
    no_deadline: 0,
  };

  return (
    <SectionCard
      title="Central de alertas"
      subtitle="Resumo operacional derivado das regras atuais do backend PHP."
    >
      <div className="pill-grid">
        <div className="pill pill-danger">Criticas {counts.critical || 0}</div>
        <div className="pill pill-warning">Hoje {counts.high || 0}</div>
        <div className="pill pill-accent">Ate 3 dias {counts.medium || 0}</div>
        <div className="pill pill-neutral">Baixa {counts.low || 0}</div>
        <div className="pill pill-soft">Sem prazo {counts.no_deadline || 0}</div>
      </div>

      <div className="stack-list">
        {notifications.length === 0 ? (
          <p className="muted-line">Nenhuma notificacao operacional no momento.</p>
        ) : (
          notifications.slice(0, 5).map((item) => (
            <article key={`${item.kind}-${item.action_id}`} className="stack-item">
              <div>
                <strong>{item.message}</strong>
                <p>{item.action_title}</p>
              </div>
              <small>{item.urgency_text || 'Sem urgencia imediata'}</small>
            </article>
          ))
        )}
      </div>
    </SectionCard>
  );
}

