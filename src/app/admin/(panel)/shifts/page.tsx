import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { listShifts } from "@/server/queries/admin-tours";
import { AdminPage, EmptyState, LinkButton } from "@/components/admin/ui";
import { TOUR_TYPE_LABELS } from "@/config/site";
import { formatPrice } from "@/lib/utils";
import { formatShiftRange } from "@/lib/shifts";
import { AvailabilitySelect } from "./availability-select";

const MONTHS = ["январь", "февраль", "март", "апрель", "май", "июнь", "июль", "август", "сентябрь", "октябрь", "ноябрь", "декабрь"];
function one(sp: Record<string, string | string[] | undefined>, k: string) { const v = sp[k]; return (Array.isArray(v) ? v[0] : v) ?? ""; }

export default async function ShiftsCalendar(props: PageProps<"/admin/shifts">) {
  const sp = await props.searchParams;
  const month = /^\d{4}-\d{2}$/.test(one(sp, "month")) ? one(sp, "month") : "";
  const type = one(sp, "type") in TOUR_TYPE_LABELS ? (one(sp, "type") as keyof typeof TOUR_TYPE_LABELS) : "";
  const region = one(sp, "region");
  const availability = one(sp, "availability");
  const q = one(sp, "q");
  const includePast = one(sp, "past") === "1";
  const [shifts, regions] = await Promise.all([
    listShifts({ month: month || null, type: (type || null) as never, region: region || null, availability: availability || null, q, includePast }),
    prisma.region.findMany({ orderBy: { sortOrder: "asc" } }),
  ]);
  // group by month
  const groups = new Map<string, typeof shifts>();
  for (const s of shifts) {
    const key = s.startDate ? `${s.startDate.getUTCFullYear()}-${String(s.startDate.getUTCMonth() + 1).padStart(2, "0")}` : "без даты";
    (groups.get(key) ?? groups.set(key, []).get(key)!).push(s);
  }
  const now = new Date();
  const monthOptions = Array.from({ length: 18 }, (_, i) => { const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - 3 + i, 1)); return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}`; });
  const label = (key: string) => (key === "без даты" ? key : `${MONTHS[Number(key.slice(5)) - 1]} ${key.slice(0, 4)}`);
  const seats = shifts.reduce((a, s) => ({ ...a, [s.availability]: (a[s.availability] ?? 0) + 1 }), {} as Record<string, number>);

  return (
    <AdminPage title="Календарь смен" description={`${shifts.length} смен · есть места ${seats.AVAILABLE ?? 0} · мало ${seats.FEW ?? 0} · нет мест ${seats.SOLD_OUT ?? 0}. Наличие мест меняется прямо здесь и сразу отображается на сайте.`} actions={<LinkButton href="/admin/tours/" variant="outline">К списку туров</LinkButton>}>
      <form method="get" className="grid gap-3 rounded-2xl bg-white p-4 shadow-card md:grid-cols-3 xl:grid-cols-6">
        <select name="month" defaultValue={month} className="input !py-2 !text-sm" aria-label="Месяц"><option value="">Все будущие</option>{monthOptions.map((m) => <option key={m} value={m}>{label(m)}</option>)}</select>
        <select name="type" defaultValue={type} className="input !py-2 !text-sm" aria-label="Тип"><option value="">Все типы</option>{Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
        <select name="region" defaultValue={region} className="input !py-2 !text-sm" aria-label="Направление"><option value="">Все направления</option><option value="foreign">За границей</option>{regions.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}</select>
        <select name="availability" defaultValue={availability} className="input !py-2 !text-sm" aria-label="Места"><option value="">Любое наличие</option><option value="AVAILABLE">Есть места</option><option value="FEW">Мало мест</option><option value="SOLD_OUT">Мест нет</option></select>
        <input name="q" defaultValue={q} placeholder="Название тура" className="input !py-2 !text-sm" />
        <div className="flex items-center gap-2"><label className="flex items-center gap-1 text-xs font-bold text-ink-500"><input type="checkbox" name="past" value="1" defaultChecked={includePast} className="accent-brand-500" /> прошедшие</label><button className="btn-primary flex-1 !py-2 !text-xs">Показать</button></div>
      </form>
      {shifts.length ? [...groups.entries()].map(([key, list]) => (
        <section key={key}>
          <h2 className="mb-2 text-lg font-extrabold capitalize text-ink-900">{label(key)} <span className="text-sm font-bold text-ink-400">· {list.length}</span></h2>
          <div className="overflow-x-auto rounded-2xl bg-white shadow-card">
            <table className="w-full min-w-[820px] text-sm">
              <thead><tr className="bg-ink-50 text-left text-xs font-extrabold uppercase tracking-wide text-ink-500"><th className="px-4 py-2.5">Даты</th><th className="px-4 py-2.5">Тур</th><th className="px-4 py-2.5">Смена</th><th className="px-4 py-2.5">Цена</th><th className="px-4 py-2.5">Места</th></tr></thead>
              <tbody>
                {list.map((s) => (
                  <tr key={s.id} className={`border-t border-ink-100 ${s.endDate && s.endDate < now ? "opacity-50" : ""}`}>
                    <td className="whitespace-nowrap px-4 py-2 font-bold text-ink-900">{formatShiftRange(s.startDate, s.endDate) ?? "—"}</td>
                    <td className="px-4 py-2"><Link href={`/admin/tours/${s.tour.id}/`} className="font-bold text-brand-600 hover:underline">{s.tour.title}</Link><div className="text-xs text-ink-400">{TOUR_TYPE_LABELS[s.tour.type]}{s.tour.region ? ` · ${s.tour.region.name}` : ""}{!s.tour.isPublished ? " · не опубликован" : ""}</div></td>
                    <td className="px-4 py-2 text-ink-700">{s.group ? <span className="text-ink-400">{s.group}: </span> : null}{s.name}</td>
                    <td className="px-4 py-2 font-bold">{s.price ? formatPrice(s.price) : "по запросу"}</td>
                    <td className="px-4 py-2"><AvailabilitySelect id={s.id} value={s.availability} seatsLeft={s.seatsLeft} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )) : <EmptyState text="Смен с такими параметрами нет" />}
    </AdminPage>
  );
}
