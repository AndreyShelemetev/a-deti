import Form from "next/form";
import { SlidersHorizontal, Search } from "lucide-react";
import { Select, TextInput } from "@/components/ui/form-fields";
import { AGE_OPTIONS, SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";
import type { TourListParams } from "@/server/queries/tours";

type Facets = { regions: { slug: string; name: string }[]; tags: { slug: string; label: string }[] };

export type FilterValues = { type?: string; region?: string; tag?: string; age?: string; season?: string; q?: string };

export function TourFilters({ facets, values, action = "/tour/", compact = false }: { facets: Facets; values: FilterValues; action?: string; compact?: boolean }) {
  return (
    <Form action={action} className="card relative overflow-hidden p-4 sm:p-6" aria-label="Подбор тура">
      <div className="blob -right-10 -top-10 h-40 w-40 bg-sun-200/60" />
      <div className="relative">
        {!compact ? (
          <div className="mb-4 flex items-center gap-2 text-lg font-extrabold text-ink-900"><SlidersHorizontal className="h-5 w-5 text-brand-500" /> Подобрать тур</div>
        ) : null}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
          <Select name="type" defaultValue={values.type ?? ""} aria-label="Тип отдыха">
            <option value="">Все типы</option>
            {Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </Select>
          <Select name="region" defaultValue={values.region ?? ""} aria-label="Регион">
            <option value="">Все регионы</option>
            {facets.regions.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}
          </Select>
          <Select name="tag" defaultValue={values.tag ?? ""} aria-label="Характеристика">
            <option value="">Все характеристики</option>
            {facets.tags.map((t) => <option key={t.slug} value={t.slug}>{t.label}</option>)}
          </Select>
          <Select name="age" defaultValue={values.age ?? ""} aria-label="Возраст ребёнка">
            <option value="">Возраст</option>
            {AGE_OPTIONS.map((a) => <option key={a} value={a}>{a} {a === 1 ? "год" : a < 5 ? "года" : "лет"}</option>)}
            <option value="21">Старше 18</option>
          </Select>
          <Select name="season" defaultValue={values.season ?? ""} aria-label="Сезон">
            <option value="">Все сезоны</option>
            {Object.entries(SEASON_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </Select>
          <button type="submit" className="btn-primary h-[52px]"><Search className="h-4 w-4" /> Подобрать</button>
        </div>
        {!compact ? (
          <div className="mt-3">
            <TextInput name="q" defaultValue={values.q ?? ""} placeholder="Поиск по названию, месту, программе…" aria-label="Поиск по турам" />
          </div>
        ) : null}
      </div>
    </Form>
  );
}

/** Parses URL search params into typed list params. */
export function parseFilterValues(sp: Record<string, string | string[] | undefined>): { values: FilterValues; params: TourListParams } {
  const one = (k: string) => { const v = sp[k]; return Array.isArray(v) ? v[0] : v; };
  const values: FilterValues = { type: one("type"), region: one("region"), tag: one("tag"), age: one("age"), season: one("season"), q: one("q") };
  const type = values.type && values.type in TOUR_TYPE_LABELS ? (values.type as TourListParams["type"]) : null;
  const season = values.season && values.season in SEASON_LABELS ? (values.season as TourListParams["season"]) : null;
  const age = values.age ? Number.parseInt(values.age, 10) : null;
  const sortRaw = one("sort");
  const sort = sortRaw === "price_asc" || sortRaw === "price_desc" || sortRaw === "new" ? sortRaw : "default";
  const page = Math.max(Number.parseInt(one("page") ?? "1", 10) || 1, 1);
  return {
    values,
    params: { type, regionSlugs: values.region ? [values.region] : [], tagSlugs: values.tag ? [values.tag] : [], season, age: age && age > 0 ? age : null, q: values.q ?? null, sort, page },
  };
}

export function hasActiveFilters(values: FilterValues) {
  return Boolean(values.type || values.region || values.tag || values.age || values.season || values.q);
}
