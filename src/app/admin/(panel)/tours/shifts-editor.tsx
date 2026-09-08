"use client";

import { useMemo, useState } from "react";
import { ArrowDown, ArrowUp, Copy, Plus, Trash2, Wand2, Percent } from "lucide-react";
import { addYears, buildShiftName, durationDays, parseShiftDates } from "@/lib/shifts";
import { formatPrice } from "@/lib/utils";

export type ShiftDraft = { group: string | null; name: string; price: number | null; startDate: string | null; endDate: string | null; availability: "AVAILABLE" | "FEW" | "SOLD_OUT"; seatsLeft: number | null };

const AVAIL: [ShiftDraft["availability"], string, string][] = [["AVAILABLE", "Есть места", "bg-mint-100 text-mint-700"], ["FEW", "Мало мест", "bg-sun-100 text-sun-600"], ["SOLD_OUT", "Мест нет", "bg-coral-100 text-coral-700"]];

export function ShiftsEditor({ value, onChange }: { value: ShiftDraft[]; onChange: (v: ShiftDraft[]) => void }) {
  const [gen, setGen] = useState({ start: "", days: 21, count: 4, gap: 0, price: "", group: "" });
  const [percent, setPercent] = useState("");
  const groups = useMemo(() => [...new Set(value.map((s) => s.group).filter(Boolean))] as string[], [value]);

  const update = (i: number, patch: Partial<ShiftDraft>) => {
    const next = value.map((s, j) => {
      if (j !== i) return s;
      const merged = { ...s, ...patch };
      // keep the name in sync with dates unless the user typed a custom name
      if (("startDate" in patch || "endDate" in patch) && merged.startDate && merged.endDate) {
        const auto = /смена/i.test(s.name) || !s.name.trim();
        if (auto) merged.name = buildShiftName(shiftIndex(value, i), merged.startDate, merged.endDate);
      }
      if ("name" in patch && patch.name && !merged.startDate) {
        const parsed = parseShiftDates(patch.name);
        if (parsed.startDate) { merged.startDate = parsed.startDate; merged.endDate = parsed.endDate; }
      }
      return merged;
    });
    onChange(next);
  };
  const shiftIndex = (arr: ShiftDraft[], i: number) => arr.slice(0, i).filter((x) => x.group === arr[i].group).length + 1;
  const move = (i: number, d: -1 | 1) => { const j = i + d; if (j < 0 || j >= value.length) return; const c = [...value]; [c[i], c[j]] = [c[j], c[i]]; onChange(c); };
  const remove = (i: number) => onChange(value.filter((_, j) => j !== i));
  const duplicate = (i: number) => {
    const s = value[i];
    const copy: ShiftDraft = { ...s, name: s.name.replace(/^(\d+)/, (m) => String(Number(m) + 1)) };
    if (s.startDate && s.endDate) {
      const len = durationDays(s.startDate, s.endDate);
      const start = new Date(s.endDate); start.setUTCDate(start.getUTCDate() + 1);
      const end = new Date(start); end.setUTCDate(end.getUTCDate() + len - 1);
      copy.startDate = start.toISOString().slice(0, 10); copy.endDate = end.toISOString().slice(0, 10);
      copy.name = buildShiftName(shiftIndex(value, i) + 1, copy.startDate, copy.endDate);
    }
    onChange([...value.slice(0, i + 1), copy, ...value.slice(i + 1)]);
  };
  const add = () => onChange([...value, { group: null, name: "", price: null, startDate: null, endDate: null, availability: "AVAILABLE", seatsLeft: null }]);
  const generate = () => {
    if (!gen.start || gen.days < 1 || gen.count < 1) return;
    const made: ShiftDraft[] = [];
    let start = new Date(gen.start);
    const existing = value.filter((s) => (s.group || null) === (gen.group || null)).length;
    for (let i = 0; i < gen.count; i++) {
      const end = new Date(start); end.setUTCDate(end.getUTCDate() + gen.days - 1);
      const sd = start.toISOString().slice(0, 10), ed = end.toISOString().slice(0, 10);
      made.push({ group: gen.group || null, name: buildShiftName(existing + i + 1, sd, ed), price: gen.price ? Number(gen.price) : null, startDate: sd, endDate: ed, availability: "AVAILABLE", seatsLeft: null });
      start = new Date(end); start.setUTCDate(start.getUTCDate() + 1 + gen.gap);
    }
    onChange([...value, ...made]);
  };
  const applyPercent = () => {
    const p = Number(percent); if (!p) return;
    onChange(value.map((s) => (s.price ? { ...s, price: Math.round((s.price * (1 + p / 100)) / 100) * 100 } : s)));
    setPercent("");
  };
  const nextSeason = () => onChange(value.map((s) => ({ ...s, startDate: s.startDate ? addYears(s.startDate, 1) : null, endDate: s.endDate ? addYears(s.endDate, 1) : null, name: s.name.replace(/\b(20\d{2})\b/g, (y) => String(Number(y) + 1)), availability: "AVAILABLE" })));

  return (
    <div className="space-y-4">
      {value.length ? (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-sm">
            <thead><tr className="text-left text-[11px] font-extrabold uppercase tracking-wide text-ink-400"><th className="px-2 py-1">Группа / корпус</th><th className="px-2 py-1">Название</th><th className="px-2 py-1">Заезд</th><th className="px-2 py-1">Выезд</th><th className="px-2 py-1">Дней</th><th className="px-2 py-1">Цена, ₽</th><th className="px-2 py-1">Места</th><th className="px-2 py-1"></th></tr></thead>
            <tbody>
              {value.map((s, i) => (
                <tr key={i} className="border-t border-ink-100 align-middle">
                  <td className="px-2 py-1.5"><input list="shift-groups" value={s.group ?? ""} onChange={(e) => update(i, { group: e.target.value || null })} placeholder="—" className="input !w-36 !py-1.5 !text-xs" /></td>
                  <td className="px-2 py-1.5"><input value={s.name} onChange={(e) => update(i, { name: e.target.value })} placeholder="1 смена 01.06–21.06.2026" className="input !min-w-56 !py-1.5 !text-xs" /></td>
                  <td className="px-2 py-1.5"><input type="date" value={s.startDate ?? ""} onChange={(e) => update(i, { startDate: e.target.value || null })} className="input !w-36 !py-1.5 !text-xs" /></td>
                  <td className="px-2 py-1.5"><input type="date" value={s.endDate ?? ""} onChange={(e) => update(i, { endDate: e.target.value || null })} className="input !w-36 !py-1.5 !text-xs" /></td>
                  <td className="px-2 py-1.5 text-center text-xs font-bold text-ink-500">{s.startDate && s.endDate ? durationDays(s.startDate, s.endDate) : "—"}</td>
                  <td className="px-2 py-1.5"><input type="number" value={s.price ?? ""} onChange={(e) => update(i, { price: e.target.value ? Number(e.target.value) : null })} placeholder="по запросу" className="input !w-28 !py-1.5 !text-xs" /></td>
                  <td className="px-2 py-1.5">
                    <div className="flex items-center gap-1">
                      <select value={s.availability} onChange={(e) => update(i, { availability: e.target.value as ShiftDraft["availability"] })} className={`rounded-lg px-2 py-1.5 text-xs font-bold ${AVAIL.find((a) => a[0] === s.availability)?.[2]}`}>{AVAIL.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
                      <input type="number" value={s.seatsLeft ?? ""} onChange={(e) => update(i, { seatsLeft: e.target.value ? Number(e.target.value) : null })} placeholder="шт." title="Осталось мест (необязательно)" className="input !w-16 !py-1.5 !text-xs" />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex gap-0.5">
                      <button type="button" onClick={() => move(i, -1)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-50" aria-label="Выше"><ArrowUp className="h-4 w-4" /></button>
                      <button type="button" onClick={() => move(i, 1)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-50" aria-label="Ниже"><ArrowDown className="h-4 w-4" /></button>
                      <button type="button" onClick={() => duplicate(i)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-50" aria-label="Следующая смена" title="Добавить следующую смену такой же длины"><Copy className="h-4 w-4" /></button>
                      <button type="button" onClick={() => remove(i)} className="rounded-lg p-1.5 text-coral-600 hover:bg-coral-50" aria-label="Удалить"><Trash2 className="h-4 w-4" /></button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <datalist id="shift-groups">{groups.map((g) => <option key={g} value={g} />)}</datalist>
        </div>
      ) : <p className="rounded-xl bg-sun-50 px-4 py-3 text-sm text-ink-600">Смен пока нет. Добавьте вручную или сгенерируйте несколько подряд.</p>}

      <div className="flex flex-wrap items-center gap-2">
        <button type="button" onClick={add} className="btn-outline !py-2 !text-xs"><Plus className="h-4 w-4" /> Добавить смену</button>
        {value.length ? <button type="button" onClick={nextSeason} className="btn-ghost !py-2 !text-xs" title="Все даты +1 год, годы в названиях +1, места обнуляются">Перенести на следующий сезон</button> : null}
        {value.some((s) => s.price) ? (
          <span className="ml-auto inline-flex items-center gap-1 text-xs font-bold text-ink-500"><Percent className="h-3.5 w-3.5" /> Изменить все цены на
            <input type="number" value={percent} onChange={(e) => setPercent(e.target.value)} placeholder="+10" className="input !w-20 !py-1.5 !text-xs" />%
            <button type="button" onClick={applyPercent} className="btn-outline !py-1.5 !text-xs">Применить</button>
          </span>
        ) : null}
      </div>

      <details className="rounded-2xl bg-ink-50 p-4">
        <summary className="cursor-pointer text-sm font-extrabold text-ink-800"><Wand2 className="mr-1 inline h-4 w-4 text-brand-500" /> Сгенерировать смены подряд</summary>
        <div className="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <label className="text-xs font-bold text-ink-500">Первый заезд<input type="date" value={gen.start} onChange={(e) => setGen({ ...gen, start: e.target.value })} className="input mt-1 !py-1.5 !text-xs" /></label>
          <label className="text-xs font-bold text-ink-500">Дней в смене<input type="number" min={1} value={gen.days} onChange={(e) => setGen({ ...gen, days: Number(e.target.value) })} className="input mt-1 !py-1.5 !text-xs" /></label>
          <label className="text-xs font-bold text-ink-500">Количество смен<input type="number" min={1} max={20} value={gen.count} onChange={(e) => setGen({ ...gen, count: Number(e.target.value) })} className="input mt-1 !py-1.5 !text-xs" /></label>
          <label className="text-xs font-bold text-ink-500">Перерыв, дней<input type="number" min={0} value={gen.gap} onChange={(e) => setGen({ ...gen, gap: Number(e.target.value) })} className="input mt-1 !py-1.5 !text-xs" /></label>
          <label className="text-xs font-bold text-ink-500">Цена, ₽<input type="number" value={gen.price} onChange={(e) => setGen({ ...gen, price: e.target.value })} className="input mt-1 !py-1.5 !text-xs" /></label>
          <label className="text-xs font-bold text-ink-500">Группа<input list="shift-groups" value={gen.group} onChange={(e) => setGen({ ...gen, group: e.target.value })} className="input mt-1 !py-1.5 !text-xs" placeholder="—" /></label>
        </div>
        <button type="button" onClick={generate} className="btn-primary mt-3 !py-2 !text-xs">Создать {gen.count} смен{gen.price ? ` по ${formatPrice(Number(gen.price))}` : ""}</button>
      </details>
    </div>
  );
}
