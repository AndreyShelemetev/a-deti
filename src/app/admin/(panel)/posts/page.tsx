import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, EmptyState, LinkButton, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDateShort } from "@/lib/utils";

export default async function PostsAdmin(props: PageProps<"/admin/posts">) {
  const sp = await props.searchParams;
  const kind = (Array.isArray(sp.kind) ? sp.kind[0] : sp.kind) === "ARTICLE" ? "ARTICLE" : "NEWS";
  const items = await prisma.post.findMany({ where: { kind }, orderBy: { publishedAt: "desc" } });
  const title = kind === "NEWS" ? "Новости" : "Статьи";
  return (
    <AdminPage title={title} description={`${items.length} записей`} actions={<LinkButton href={`/admin/posts/new/?kind=${kind}`}>+ Добавить</LinkButton>}>
      {items.length ? (
        <Table>
          <thead><tr><Th>Дата</Th><Th>Заголовок</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {items.map((p) => (
              <tr key={p.id} className="hover:bg-brand-50/40">
                <Td className="whitespace-nowrap text-xs text-ink-500">{formatDateShort(p.publishedAt)}</Td>
                <Td><Link href={`/admin/posts/${p.id}/`} className="font-bold text-brand-600 hover:underline">{p.title}</Link><div className="text-xs text-ink-400">/{kind === "NEWS" ? "novosti" : "stati"}/{p.slug}/</div></Td>
                <Td><StatusBadge value={p.isPublished} label={p.isPublished ? "Опубликовано" : "Черновик"} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      ) : <EmptyState />}
    </AdminPage>
  );
}
