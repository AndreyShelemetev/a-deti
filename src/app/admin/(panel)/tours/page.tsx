import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, EmptyState, LinkButton, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { TOUR_TYPE_LABELS } from "@/config/site";
import { formatPrice } from "@/lib/utils";

export default async function ToursAdmin(props: PageProps<"/admin/tours">) {
  const sp = await props.searchParams;
  const q = ((Array.isArray(sp.q) ? sp.q[0] : sp.q) ?? "").trim();
  const items = await prisma.tour.findMany({
    where: q ? { OR: [{ title: { contains: q, mode: "insensitive" } }, { slug: { contains: q, mode: "insensitive" } }] } : undefined,
    orderBy: [{ isPublished: "desc" }, { sortOrder: "asc" }, { id: "asc" }],
    include: { region: true, _count: { select: { shifts: true, images: true, requests: true } } },
  });
  return (
    <AdminPage title="Туры" description={`${items.length} туров. Порядок в каталоге задаётся полем «Сортировка».`} actions={<LinkButton href="/admin/tours/new/">+ Добавить тур</LinkButton>}>
      <form className="flex gap-2"><input name="q" defaultValue={q} placeholder="Поиск по названию" className="input !w-80 !py-2 !text-sm" /><button className="btn-outline !py-2 !text-xs">Найти</button></form>
      {items.length ? (
        <Table>
          <thead><tr><Th>Название</Th><Th>Тип / регион</Th><Th>Цена от</Th><Th>Смен / фото</Th><Th>Заявки</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {items.map((t) => (
              <tr key={t.id} className="hover:bg-brand-50/40">
                <Td><Link href={`/admin/tours/${t.id}/`} className="font-bold text-brand-600 hover:underline">{t.title}</Link><div className="text-xs text-ink-400">/tour/{t.slug}/ {t.isBest ? "· ★ лучшее" : ""} {t.isHit ? "· 🔥 хит" : ""}</div></Td>
                <Td>{TOUR_TYPE_LABELS[t.type]}<div className="text-xs text-ink-500">{t.region?.name ?? "—"}</div></Td>
                <Td>{t.priceMin ? formatPrice(t.priceMin) : t.priceText ?? "—"}</Td>
                <Td>{t._count.shifts} / {t._count.images}</Td>
                <Td>{t._count.requests}</Td>
                <Td><StatusBadge value={t.isPublished} label={t.isPublished ? "Опубликован" : "Скрыт"} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      ) : <EmptyState />}
    </AdminPage>
  );
}
