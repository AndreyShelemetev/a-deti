import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, LinkButton, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDateShort } from "@/lib/utils";

export default async function PagesAdmin() {
  const items = await prisma.page.findMany({ orderBy: { slug: "asc" } });
  return (
    <AdminPage title="Текстовые страницы" description="Родителям, агентствам, оплата, политика и другие информационные страницы." actions={<LinkButton href="/admin/pages/new/">+ Добавить</LinkButton>}>
      <Table>
        <thead><tr><Th>Страница</Th><Th>Адрес</Th><Th>Обновлена</Th><Th>Статус</Th></tr></thead>
        <tbody>
          {items.map((p) => (
            <tr key={p.id} className="hover:bg-brand-50/40">
              <Td><Link href={`/admin/pages/${p.id}/`} className="font-bold text-brand-600 hover:underline">{p.title}</Link></Td>
              <Td className="text-xs text-ink-500">/{p.slug}/</Td>
              <Td className="text-xs text-ink-500">{formatDateShort(p.updatedAt)}</Td>
              <Td><StatusBadge value={p.isPublished} label={p.isPublished ? "Опубликована" : "Скрыта"} /></Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </AdminPage>
  );
}
