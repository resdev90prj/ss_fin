export default function LoadingState({ title = 'Carregando dados', text = 'Aguarde um instante.' }) {
  return (
    <div className="state-card">
      <div className="spinner" aria-hidden="true" />
      <div>
        <h2>{title}</h2>
        <p>{text}</p>
      </div>
    </div>
  );
}

