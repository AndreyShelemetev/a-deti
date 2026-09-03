import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { DangerAction } from "@/components/admin/form";
import { CatalogForm } from "../catalog-form";
import { deleteCatalogPage } from "@/server/actions/admin/content";

export default async function EditCatalogPage(props: PageProps<"/admin/catalog-pages/[id]">) {
  const { id } = await props.params;
  const [page, regions, tags] = await Promise.all([prisma.catalogPage.findUnique({ where: { id: Number(id) } }), prisma.region.findMany({ orderBy: { sortOrder: "asc" } }), prisma.tag.findMany({ orderBy: { sortOrder: "asc" } })]);
  if (!page) notFound();
  const remove = deleteCatalogPage.bind(null, page.id);
  return (
    <AdminPage title={`/tour/filter/${page.path}/`} actions={<><Link href={`/tour/filter/${page.path}/`} target="_blank" className="btn-outline !py-2 !text-xs">Открыть на сайте</Link><Link href="/admin/catalog-pages/" className="btn-ghost !py-2 !text-xs">← К списку</Link></>}>
      <CatalogForm page={page} regions={regions} tags={tags} />
      <div className="border-t border-ink-200 pt-4"><DangerAction action={remove} /></div>
    </AdminPage>
  );
}
