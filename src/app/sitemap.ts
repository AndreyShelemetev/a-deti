import type { MetadataRoute } from "next";
import { absoluteUrl } from "@/lib/utils";
import { getPublishedTourSlugs } from "@/server/queries/tours";
import { getPublishedPostSlugs } from "@/server/queries/posts";
import { getPublishedPageSlugs } from "@/server/queries/pages";
import { listCatalogPages } from "@/server/queries/catalog";

export const dynamic = "force-dynamic";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [tours, posts, pages, catalog] = await Promise.all([getPublishedTourSlugs(), getPublishedPostSlugs(), getPublishedPageSlugs(), listCatalogPages()]);
  const now = new Date();
  const staticRoutes: MetadataRoute.Sitemap = [
    { url: absoluteUrl("/"), lastModified: now, changeFrequency: "daily", priority: 1 },
    { url: absoluteUrl("/tour/"), lastModified: now, changeFrequency: "daily", priority: 0.9 },
    { url: absoluteUrl("/novosti/"), lastModified: now, changeFrequency: "weekly", priority: 0.6 },
    { url: absoluteUrl("/stati/"), lastModified: now, changeFrequency: "monthly", priority: 0.5 },
  ];
  return [
    ...staticRoutes,
    ...catalog.map((c) => ({ url: absoluteUrl(`/tour/filter/${c.path}/`), lastModified: now, changeFrequency: "weekly" as const, priority: 0.8 })),
    ...tours.map((t) => ({ url: absoluteUrl(`/tour/${t.slug}/`), lastModified: t.updatedAt, changeFrequency: "weekly" as const, priority: 0.8 })),
    ...posts.map((p) => ({ url: absoluteUrl(`/${p.kind === "NEWS" ? "novosti" : "stati"}/${p.slug}/`), lastModified: p.updatedAt, changeFrequency: "monthly" as const, priority: 0.5 })),
    ...pages.filter((p) => !p.slug.startsWith("pay")).map((p) => ({ url: absoluteUrl(`/${p.slug}/`), lastModified: p.updatedAt, changeFrequency: "monthly" as const, priority: 0.4 })),
  ];
}
