import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { DangerAction } from "@/components/admin/form";
import { PageForm } from "../page-form";
import { deletePage } from "@/server/actions/admin/content";

export default async function EditPage(props: PageProps<"/admin/pages/[id]">) {
  const { id } = await props.params;
  const page = await prisma.page.findUnique({ where: { id: Number(id) } });
  if (!page) notFound();
  const remove = deletePage.bind(null, page.id);
  return (
    <AdminPage title={page.title} actions={<><Link href={`/${page.slug}/`} target="_blank" className="btn-outline !py-2 !text-xs">Открыть на сайте</Link><Link href="/admin/pages/" className="btn-ghost !py-2 !text-xs">← К списку</Link></>}>
      <PageForm page={page} />
      <div className="border-t border-ink-200 pt-4"><DangerAction action={remove} /></div>
    </AdminPage>
  );
}
