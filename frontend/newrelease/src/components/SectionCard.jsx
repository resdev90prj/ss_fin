export default function SectionCard({ title, subtitle, action, children, className = '' }) {
  return (
    <section className={`section-card ${className}`.trim()}>
      {(title || subtitle || action) && (
        <header className="section-card__header">
          <div>
            {title ? <h2>{title}</h2> : null}
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          {action ? <div className="section-card__action">{action}</div> : null}
        </header>
      )}
      {children}
    </section>
  );
}

