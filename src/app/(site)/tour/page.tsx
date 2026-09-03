import type { Metadata } from "next";
import Link from "next/link";
import { buildMetadata } from "@/lib/seo";
import { plural } from "@/lib/utils";
import { getFilterFacets, listTours } from "@/server/queries/tours";
import { getSettings } from "@/server/queries/settings";
import { TourFilters, hasActiveFilters, parseFilterValues } from "@/components/tour/tour-filters";
import { TourList } from "@/components/tour/tour-list";
import { SortSelect } from "@/components/tour/sort-select";
import { Pagination } from "@/components/ui/pagination";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { HelpCard } from "@/components/layout/help-card";
import { SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";

export async function generateMetadata(props: PageProps<"/tour">): Promise<Metadata> {
  const sp = await props.searchParams;
  const { values } = parseFilterValues(sp);
  const filtered = hasActiveFilters(values) || Boolean(sp.page) || Boolean(sp.sort);
  return buildMetadata({
    title: "Все туры и детские лагеря — каталог",
    description: "Каталог детских лагерей, спортивных сборов, экскурсионных и семейных туров от туроператора А-тур. Подбор по возрасту, региону, сезону и интересам ребёнка.",
    path: "/tour/",
    noindex: filtered,
  });
}

export default async function CatalogPage(props: PageProps<"/tour">) {
  const sp = await props.searchParams;
  const { values, params } = parseFilterValues(sp);
  const [facets, result, settings] = await Promise.all([getFilterFacets(), listTours({ ...params, perPage: 12 }), getSettings()]);
  const active = hasActiveFilters(values);
  const hrefFor = (page: number) => {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(values)) if (v) q.set(k, v);
    if (params.sort && params.sort !== "default") q.set("sort", params.sort);
    if (page > 1) q.set("page", String(page));
    const s = q.toString();
    return `/tour/${s ? `?${s}` : ""}`;
  };
  const chips = [
    values.type ? TOUR_TYPE_LABELS[values.type] : null,
    values.region ? facets.regions.find((r) => r.slug === values.region)?.name : null,
    values.tag ? facets.tags.find((t) => t.slug === values.tag)?.label : null,
    values.age ? `${values.age} лет` : null,
    values.season ? SEASON_LABELS[values.season] : null,
    values.q ? `«${values.q}»` : null,
  ].filter(Boolean);

  return (
    <div className="container-site py-6 sm:py-8">
      <Breadcrumbs items={[{ name: "Все туры" }]} />
      <h1 className="text-3xl font-black text-ink-900 sm:text-4xl lg:text-5xl">Все туры</h1>
      <p className="mt-3 max-w-3xl text-lg text-ink-600">Детские лагеря в России и за рубежом, спортивные сборы, экскурсии и семейный отдых. Выберите параметры — покажем подходящие программы.</p>
      <div className="mt-6"><TourFilters facets={facets} values={values} /></div>
      <div className="mt-8 grid gap-8 lg:grid-cols-12">
        <div className="lg:col-span-9">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div className="text-sm font-bold text-ink-600">
              Найдено <span className="text-ink-900">{result.total}</span> {plural(result.total, ["тур", "тура", "туров"])}
              {active ? <span className="ml-2 text-ink-400">по запросу: {chips.join(" · ")} · <Link href="/tour/" className="font-extrabold text-brand-600 hover:underline">сбросить</Link></span> : null}
            </div>
            <SortSelect />
          </div>
          <TourList items={result.items} />
          <Pagination page={result.page} pages={result.pages} hrefFor={hrefFor} />
        </div>
        <div className="lg:col-span-3"><HelpCard settings={settings} /></div>
      </div>
    </div>
  );
}
