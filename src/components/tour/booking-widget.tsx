"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { CalendarDays, CreditCard, Phone, Printer } from "lucide-react";
import { Select } from "@/components/ui/form-fields";
import { BookingButton, CallbackButton } from "@/components/forms/lead-modals";
import { formatPrice } from "@/lib/utils";
import { formatShiftRange } from "@/lib/shifts";

type Shift = { id: number; group: string | null; name: string; price: number | null; priceText: string | null; availability: "AVAILABLE" | "FEW" | "SOLD_OUT"; seatsLeft: number | null; startDate: Date | null; endDate: Date | null };
const AVAIL_LABEL = { AVAILABLE: "", FEW: " · мало мест", SOLD_OUT: " · мест нет" } as const;
type Props = { tourId: number; tourTitle: string; shifts: Shift[]; priceText: string | null; priceMin: number | null };

export function BookingWidget({ tourId, tourTitle, shifts, priceText, priceMin }: Props) {
  const firstOpen = shifts.find((s) => s.availability !== "SOLD_OUT") ?? shifts[0];
  const [selectedId, setSelectedId] = useState<number | null>(firstOpen?.id ?? null);
  const selected = shifts.find((s) => s.id === selectedId) ?? null;
  const groups = useMemo(() => {
    const map = new Map<string, Shift[]>();
    for (const s of shifts) { const k = s.group ?? ""; (map.get(k) ?? map.set(k, []).get(k)!).push(s); }
    return [...map.entries()];
  }, [shifts]);
  const price = selected?.price ?? null;
  const shiftLabel = selected ? `${selected.group ? `${selected.group}: ` : ""}${selected.name}` : null;

  return (
    <div className="card sticky top-24 p-5 sm:p-6">
      <div className="mb-3 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-ink-500"><CalendarDays className="h-4 w-4 text-brand-500" /> Смены и цены</div>
      {shifts.length ? (
        <Select value={selectedId ?? ""} onChange={(e) => setSelectedId(Number(e.target.value))} aria-label="Выберите смену" className="mb-4">
          {groups.map(([group, items]) =>
            group ? (
              <optgroup key={group} label={group}>{items.map((s) => <option key={s.id} value={s.id}>{s.name}{s.price ? ` — ${formatPrice(s.price)}` : ""}{AVAIL_LABEL[s.availability]}</option>)}</optgroup>
            ) : (
              items.map((s) => <option key={s.id} value={s.id}>{s.name}{s.price ? ` — ${formatPrice(s.price)}` : ""}{AVAIL_LABEL[s.availability]}</option>)
            ),
          )}
        </Select>
      ) : null}
      {selected ? (
        <div className="mb-3 flex flex-wrap items-center gap-2 text-xs font-bold">
          {selected.startDate && selected.endDate ? <span className="chip bg-ink-100 text-ink-700">{formatShiftRange(selected.startDate, selected.endDate)}</span> : null}
          {selected.availability === "AVAILABLE" ? <span className="chip bg-mint-100 text-mint-700">Есть места</span> : selected.availability === "FEW" ? <span className="chip bg-sun-100 text-sun-600">Мало мест{selected.seatsLeft ? `: ${selected.seatsLeft}` : ""}</span> : <span className="chip bg-coral-100 text-coral-700">Мест нет</span>}
        </div>
      ) : null}
      <div className="mb-4 rounded-2xl bg-brand-50 px-4 py-3">
        <div className="text-xs font-bold uppercase tracking-wide text-ink-500">Стоимость</div>
        <div className="text-3xl font-black text-brand-700">
          {price ? formatPrice(price) : priceMin ? `от ${formatPrice(priceMin)}` : priceText && !/запрос/i.test(priceText) ? priceText : "по запросу"}
        </div>
        {!price ? <div className="mt-1 text-xs text-ink-500">Точную стоимость уточнит менеджер</div> : null}
      </div>
      <div className="flex flex-col gap-2">
        <BookingButton className="btn-primary w-full" tourId={tourId} tourTitle={tourTitle} shiftName={shiftLabel} amount={price}>{selected?.availability === "SOLD_OUT" ? "Записаться в лист ожидания" : "Отправить заявку"}</BookingButton>
        <Link href={`/pay/?amount=${price ?? ""}`} className="btn-outline w-full"><CreditCard className="h-4 w-4" /> Оплатить онлайн</Link>
        <CallbackButton className="btn-ghost w-full"><Phone className="h-4 w-4" /> Заказать звонок</CallbackButton>
        <button type="button" onClick={() => window.print()} className="btn-ghost w-full !text-xs text-ink-500 print:hidden"><Printer className="h-4 w-4" /> Версия для печати</button>
      </div>
      <p className="mt-4 text-center text-xs text-ink-400">Бронирование бесплатно и ни к чему не обязывает. Менеджер подтвердит наличие мест.</p>
    </div>
  );
}
