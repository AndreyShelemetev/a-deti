import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getAdminTourFacets, listAdminTours, type AdminTourFilters } from "@/server/queries/admin-tours";
import { AdminPage, LinkButton } from "@/components/admin/ui";
import { Pagination } from "@/components/ui/pagination";
import { SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";
import { TourTable } from "./tour-table";
import { Search, X } from "lucide-react";

const TYPES = Object.keys(TOUR_TYPE_LABELS) as AdminTourFilters["type"][];
const STATUS_TABS: [NonNullable<AdminTourFilters["status"]>, string][] = [["active", "Актуальные"], ["published", "На сайте"], ["draft", "Черновики"], ["archived", "Архив"], ["all", "Все"]];
const FLAGS: [NonNullable<AdminTourFilters["flag"]>, string][] = [["best", "★ Лучшие"], ["hit", "🔥 Хиты"], ["no_cover", "Без обложки"], ["no_shifts", "Без смен"], ["no_price", "Без цены"], ["past", "Смены прошли"]];

function one(sp: Record<string, string | string[] | undefined>, k: string) { const v = sp[k]; return (Array.isArray(v) ? v[0] : v) ?? ""; }

export default async function ToursAdmin(props: PageProps<"/admin/tours">) {
  const sp = await props.searchParams;
  const f: AdminTourFilters = {
    q: one(sp, "q"),
    type: TYPES.includes(one(sp, "type") as AdminTourFilters["type"]) ? (one(sp, "type") as AdminTourFilters["type"]) : null,
    region: one(sp, "region") || null,
    season: (one(sp, "season") in SEASON_LABELS ? one(sp, "season") : null) as AdminTourFilters["season"],
    tag: one(sp, "tag") || null,
    status: (STATUS_TABS.some(([k]) => k === one(sp, "status")) ? one(sp, "status") : "active") as AdminTourFilters["status"],
    from: one(sp, "from") || null,
    to: one(sp, "to") || null,
    priceMin: one(sp, "priceMin") ? Number(one(sp, "priceMin")) : null,
    priceMax: one(sp, "priceMax") ? Number(one(sp, "priceMax")) : null,
    flag: (FLAGS.some(([k]) => k === one(sp, "flag")) ? one(sp, "flag") : null) as AdminTourFilters["flag"],
    sort: (["updated", "title", "price", "order", "next_shift"].includes(one(sp, "sort")) ? one(sp, "sort") : "order") as AdminTourFilters["sort"],
    page: Math.max(Number(one(sp, "page")) || 1, 1),
  };
  const [result, facets, regions, tags] = await Promise.all([
    listAdminTours(f), getAdminTourFacets(),
    prisma.region.findMany({ orderBy: { sortOrder: "asc" } }), prisma.tag.findMany({ orderBy: { sortOrder: "asc" }, select: { slug: true, label: true } }),
  ]);
  const qs = (patch: Record<string, string | number | null | undefined>) => {
    const q = new URLSearchParams();
    const merged: Record<string, string | number | null | undefined> = { q: f.q, type: f.type, region: f.region, season: f.season, tag: f.tag, status: f.status, from: f.from, to: f.to, priceMin: f.priceMin, priceMax: f.priceMax, flag: f.flag, sort: f.sort, ...patch };
    for (const [k, v] of Object.entries(merged)) if (v !== null && v !== undefined && v !== "" && !(k === "status" && v === "active") && !(k === "sort" && v === "order")) q.set(k, String(v));
    const s = q.toString();
    return `/admin/tours/${s ? `?${s}` : ""}`;
  };
  const activeFilters = [f.q, f.region, f.season, f.tag, f.from, f.to, f.priceMin, f.priceMax, f.flag].filter((v) => v !== null && v !== undefined && v !== "").length;

  return (
    <AdminPage
      title={f.type ? TOUR_TYPE_LABELS[f.type] : "Все туры"}
      description={`${result.total} из ${facets.total} актуальных · на сайте ${facets.published} · черновиков ${facets.draft} · в архиве ${facets.archived}`}
      actions={<><Link href="/admin/shifts/" className="btn-outline !py-2 !text-xs">Календарь смен</Link><LinkButton href={`/admin/tours/new/${f.type ? `?type=${f.type}` : ""}`}>+ Добавить тур</LinkButton></>}
    >
      {/* category tabs */}
      <div className="flex flex-wrap gap-2">
        <Link href={qs({ type: null, page: null })} className={`chip ${!f.type ? "bg-ink-900 text-white" : "bg-white text-ink-700 shadow-card hover:bg-brand-50"}`}>Все <span className="opacity-60">{facets.total}</span></Link>
        {TYPES.map((t) => t && (
          <Link key={t} href={qs({ type: t, page: null })} className={`chip ${f.type === t ? "bg-ink-900 text-white" : "bg-white text-ink-700 shadow-card hover:bg-brand-50"}`}>{TOUR_TYPE_LABELS[t]} <span className="opacity-60">{facets.byType[t] ?? 0}</span></Link>
        ))}
        <span className="mx-1 self-center text-ink-300">|</span>
        {STATUS_TABS.map(([k, l]) => (
          <Link key={k} href={qs({ status: k, page: null })} className={`chip ${f.status === k ? "bg-brand-500 text-white" : "bg-white text-ink-700 shadow-card hover:bg-brand-50"}`}>{l}</Link>
        ))}
      </div>

      {/* filters */}
      <form className="rounded-2xl bg-white p-4 shadow-card" method="get">
        {f.type ? <input type="hidden" name="type" value={f.type} /> : null}
        {f.status && f.status !== "active" ? <input type="hidden" name="status" value={f.status} /> : null}
        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          <div className="md:col-span-3 xl:col-span-2"><input name="q" defaultValue={f.q} placeholder="Название, адрес, slug" className="input !py-2 !text-sm" aria-label="Поиск" /></div>
          <select name="region" defaultValue={f.region ?? ""} className="input !py-2 !text-sm" aria-label="Направление">
            <option value="">Все направления</option>
            <option value="russia_all">Россия (все регионы)</option>
            <option value="foreign">За границей (все страны)</option>
            {regions.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}
          </select>
          <select name="season" defaultValue={f.season ?? ""} className="input !py-2 !text-sm" aria-label="Сезон"><option value="">Все сезоны</option>{Object.entries(SEASON_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
          <select name="tag" defaultValue={f.tag ?? ""} className="input !py-2 !text-sm" aria-label="Характеристика"><option value="">Все характеристики</option>{tags.map((t) => <option key={t.slug} value={t.slug}>{t.label}</option>)}</select>
          <select name="flag" defaultValue={f.flag ?? ""} className="input !py-2 !text-sm" aria-label="Особые"><option value="">Особые отметки</option>{FLAGS.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
          <div className="flex items-center gap-2 xl:col-span-2"><span className="shrink-0 text-xs font-bold text-ink-500">Смены</span><input type="date" name="from" defaultValue={f.from ?? ""} className="input !py-2 !text-sm" aria-label="Смены с" /><span className="text-ink-300">—</span><input type="date" name="to" defaultValue={f.to ?? ""} className="input !py-2 !text-sm" aria-label="Смены по" /></div>
          <div className="flex items-center gap-2 xl:col-span-2"><span className="shrink-0 text-xs font-bold text-ink-500">Цена</span><input type="number" name="priceMin" defaultValue={f.priceMin ?? ""} className="input !py-2 !text-sm" placeholder="от" aria-label="Цена от" /><span className="text-ink-300">—</span><input type="number" name="priceMax" defaultValue={f.priceMax ?? ""} className="input !py-2 !text-sm" placeholder="до" aria-label="Цена до" /></div>
          <select name="sort" defaultValue={f.sort} className="input !py-2 !text-sm" aria-label="Сортировка">
            <option value="order">По порядку на сайте</option><option value="next_shift">По ближайшей смене</option><option value="updated">Недавно изменённые</option><option value="title">По названию</option><option value="price">По цене</option>
          </select>
          <div className="flex gap-2">
            <button type="submit" className="btn-primary flex-1 !py-2 !text-xs"><Search className="h-4 w-4" /> Показать</button>
            {activeFilters ? <Link href={qs({ q: null, region: null, season: null, tag: null, from: null, to: null, priceMin: null, priceMax: null, flag: null, page: null })} className="btn-ghost !px-3 !py-2 !text-xs" title="Сбросить фильтры"><X className="h-4 w-4" /></Link> : null}
          </div>
        </div>
      </form>

      <TourTable items={result.items} status={f.status ?? "active"} />
      <Pagination page={result.page} pages={result.pages} hrefFor={(p) => qs({ page: p > 1 ? p : null })} className="!mt-2" />
    </AdminPage>
  );
}
