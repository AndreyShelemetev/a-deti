import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { CatalogForm } from "../catalog-form";

export default async function NewCatalogPage() {
  const [regions, tags] = await Promise.all([prisma.region.findMany({ orderBy: { sortOrder: "asc" } }), prisma.tag.findMany({ orderBy: { sortOrder: "asc" } })]);
  return (
    <AdminPage title="Новая страница каталога">
      <CatalogForm page={{ path: "", h1: "", seoTitle: null, metaDescription: null, seoText: null, type: null, regionSlugs: [], tagSlugs: [], season: null, isIndexable: true, sortOrder: 100 }} regions={regions} tags={tags} />
    </AdminPage>
  );
}
