import { LoaderCircle } from 'lucide-react';
import { Card } from './ui/card';

export default function LoadingState({ title = 'Carregando dados', text = 'Aguarde um instante.' }) {
  return (
    <Card className="state-card">
      <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary" aria-hidden="true">
        <LoaderCircle className="h-6 w-6 animate-spin" />
      </div>
      <div>
        <h2 className="text-lg font-bold tracking-tight text-slate-950">{title}</h2>
        <p className="mt-1 text-sm text-slate-600">{text}</p>
      </div>
    </Card>
  );
}
