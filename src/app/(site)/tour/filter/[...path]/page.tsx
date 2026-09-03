import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { buildMetadata } from "@/lib/seo";
import { plural, withYear } from "@/lib/utils";
import { getCatalogPage, getTagBySlug } from "@/server/queries/catalog";
import { getFilterFacets, listTours, type TourListParams } from "@/server/queries/tours";
import { getSettings } from "@/server/queries/settings";
import { TourFilters, parseFilterValues } from "@/components/tour/tour-filters";
import { TourList } from "@/components/tour/tour-list";
import { SortSelect } from "@/components/tour/sort-select";
import { Pagination } from "@/components/ui/pagination";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { HelpCard } from "@/components/layout/help-card";
import { RichText } from "@/components/ui/rich-text";

type Resolved = { h1: string; seoTitle: string | null; metaDescription: string | null; seoText: string | null; params: TourListParams };

/** Resolves a SEF path like "lager/crymea" to an admin-defined catalog page, or falls back to a plain tag page. */
async function resolve(path: string): Promise<Resolved | null> {
  const page = await getCatalogPage(path);
  if (page) {
    return { h1: page.h1, seoTitle: page.seoTitle, metaDescription: page.metaDescription, seoText: page.seoText, params: { type: page.type, regionSlugs: page.regionSlugs, tagSlugs: page.tagSlugs, season: page.season } };
  }
  if (!path.includes("/")) {
    const tag = await getTagBySlug(path);
    if (tag) return { h1: tag.h1 ?? `Детский отдых: ${tag.label}, {year}`, seoTitle: tag.seoTitle, metaDescription: tag.metaDescription, seoText: null, params: { tagSlugs: [tag.slug] } };
  }
  return null;
}

export async function generateMetadata(props: PageProps<"/tour/filter/[...path]">): Promise<Metadata> {
  const { path } = await props.params;
  const sp = await props.searchParams;
  const resolved = await resolve(path.join("/"));
  if (!resolved) return {};
  const h1 = withYear(resolved.h1);
  return buildMetadata({
    title: withYear(resolved.seoTitle) || `${h1}. Заказать и купить путевку в Москве`,
    description: withYear(resolved.metaDescription) || `${h1}. Описание туров, фото, отзывы, цены. Подбор и бронирование у туроператора А-тур.`,
    path: `/tour/filter/${path.join("/")}/`,
    noindex: Boolean(sp.page || sp.sort),
  });
}

export default async function CatalogFilterPage(props: PageProps<"/tour/filter/[...path]">) {
  const { path } = await props.params;
  const sp = await props.searchParams;
  const key = path.join("/");
  const resolved = await resolve(key);
  if (!resolved) notFound();
  const { params: extra } = parseFilterValues(sp);
  const [facets, result, settings] = await Promise.all([
    getFilterFacets(),
    listTours({ ...resolved.params, age: extra.age, sort: extra.sort, page: extra.page, perPage: 12 }),
    getSettings(),
  ]);
  const h1 = withYear(resolved.h1);
  const hrefFor = (page: number) => {
    const q = new URLSearchParams();
    if (extra.sort && extra.sort !== "default") q.set("sort", extra.sort);
    if (page > 1) q.set("page", String(page));
    const s = q.toString();
    return `/tour/filter/${key}/${s ? `?${s}` : ""}`;
  };
  const values = {
    type: resolved.params.type ?? undefined,
    region: resolved.params.regionSlugs?.length === 1 ? resolved.params.regionSlugs[0] : undefined,
    tag: resolved.params.tagSlugs?.[0],
    season: resolved.params.season ?? undefined,
  };
  return (
    <div className="container-site py-6 sm:py-8">
      <Breadcrumbs items={[{ name: "Все туры", href: "/tour/" }, { name: h1 }]} />
      <h1 className="text-3xl font-black text-ink-900 sm:text-4xl lg:text-5xl">{h1}</h1>
      <p className="mt-3 max-w-3xl text-lg text-ink-600">Подобрали {result.total} {plural(result.total, ["программу", "программы", "программ"])}. Уточните возраст ребёнка или сезон, чтобы сузить выбор.</p>
      <div className="mt-6"><TourFilters facets={facets} values={values} compact /></div>
      <div className="mt-8 grid gap-8 lg:grid-cols-12">
        <div className="lg:col-span-9">
          <div className="mb-4 flex items-center justify-end"><SortSelect /></div>
          <TourList items={result.items} />
          <Pagination page={result.page} pages={result.pages} hrefFor={hrefFor} />
          {resolved.seoText ? <div className="card mt-10 p-6 sm:p-8"><RichText html={withYear(resolved.seoText)} /></div> : null}
        </div>
        <div className="lg:col-span-3"><HelpCard settings={settings} /></div>
      </div>
    </div>
  );
}
