import { groupModuleOptions } from './moduleAccess';

export default function UserModuleSelector({
  moduleOptions = [],
  selection = {},
  onToggle,
  disabled = false,
}) {
  const groupedOptions = Object.values(groupModuleOptions(moduleOptions));

  return (
    <div className="space-y-4">
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        Dashboard e Meu acesso permanecem disponiveis como base. Os modulos abaixo controlam o restante da experiencia.
      </div>

      {groupedOptions.map((group) => (
        <div key={group.key} className="rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
          <div className="mb-3">
            <h4 className="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">{group.label}</h4>
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            {group.items.map((moduleOption) => (
              <label
                key={moduleOption.key}
                className={`rounded-2xl border px-4 py-4 transition ${
                  selection[moduleOption.key]
                    ? 'border-slate-950 bg-white shadow-sm'
                    : 'border-slate-200 bg-white/70'
                } ${disabled ? 'opacity-70' : 'cursor-pointer hover:border-slate-300'}`}
              >
                <div className="flex items-start gap-3">
                  <input
                    type="checkbox"
                    className="mt-1 h-4 w-4"
                    checked={Boolean(selection[moduleOption.key])}
                    onChange={() => onToggle(moduleOption.key)}
                    disabled={disabled}
                  />
                  <div>
                    <strong className="text-sm font-semibold text-slate-950">{moduleOption.label}</strong>
                    <p className="mt-1 text-sm text-slate-600">{moduleOption.description}</p>
                  </div>
                </div>
              </label>
            ))}
          </div>
        </div>
      ))}

      {groupedOptions.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
          Nenhum modulo configuravel foi encontrado para este ambiente.
        </div>
      ) : null}
    </div>
  );
}
