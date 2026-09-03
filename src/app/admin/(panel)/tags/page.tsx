import { prisma } from "@/lib/prisma";
import { AdminPage, Panel } from "@/components/admin/ui";
import { TaxonomyEditor } from "./taxonomy-editor";

export default async function TagsAdmin() {
  const [tags, regions] = await Promise.all([
    prisma.tag.findMany({ orderBy: { sortOrder: "asc" }, include: { _count: { select: { tours: true } } } }),
    prisma.region.findMany({ orderBy: { sortOrder: "asc" }, include: { _count: { select: { tours: true } } } }),
  ]);
  return (
    <AdminPage title="Характеристики и регионы" description="Характеристики (теги) используются в фильтре и на страницах /tour/filter/<slug>/. Регионы — в фильтре и правилах SEO-страниц.">
      <div className="grid gap-6 xl:grid-cols-2">
        <Panel title="Характеристики"><TaxonomyEditor kind="tag" items={tags.map((t) => ({ id: t.id, slug: t.slug, label: t.label, h1: t.h1, seoTitle: t.seoTitle, metaDescription: t.metaDescription, sortOrder: t.sortOrder, count: t._count.tours }))} /></Panel>
        <Panel title="Регионы"><TaxonomyEditor kind="region" items={regions.map((r) => ({ id: r.id, slug: r.slug, label: r.name, isForeign: r.isForeign, sortOrder: r.sortOrder, count: r._count.tours }))} /></Panel>
      </div>
    </AdminPage>
  );
}
