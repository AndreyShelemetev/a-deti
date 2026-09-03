import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, EmptyState, LinkButton, Table, Td, Th } from "@/components/admin/ui";
import { formatDateShort } from "@/lib/utils";
import { PublishToggle } from "./publish-toggle";

export default async function ReviewsAdmin(props: PageProps<"/admin/reviews">) {
  const sp = await props.searchParams;
  const onlyPending = (Array.isArray(sp.published) ? sp.published[0] : sp.published) === "0";
  const items = await prisma.review.findMany({ where: onlyPending ? { isPublished: false } : undefined, orderBy: [{ isPublished: "asc" }, { publishedAt: "desc" }], include: { tour: { select: { title: true } } } });
  return (
    <AdminPage title="Отзывы" description="Отзывы с сайта попадают сюда на модерацию и публикуются после проверки." actions={<><Link href={onlyPending ? "/admin/reviews/" : "/admin/reviews/?published=0"} className="btn-outline !py-2 !text-xs">{onlyPending ? "Показать все" : "Только на модерации"}</Link><LinkButton href="/admin/reviews/new/">+ Добавить</LinkButton></>}>
      {items.length ? (
        <Table>
          <thead><tr><Th>Дата</Th><Th>Автор</Th><Th>Текст</Th><Th>Оценка</Th><Th>Источник</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {items.map((r) => (
              <tr key={r.id} className="hover:bg-brand-50/40">
                <Td className="whitespace-nowrap text-xs text-ink-500">{formatDateShort(r.publishedAt)}</Td>
                <Td><Link href={`/admin/reviews/${r.id}/`} className="font-bold text-brand-600 hover:underline">{r.author}</Link>{r.tour ? <div className="text-xs text-ink-400">{r.tour.title}</div> : null}</Td>
                <Td className="max-w-md"><span className="line-clamp-2">{r.text}</span></Td>
                <Td>{"★".repeat(r.rating)}</Td>
                <Td className="text-xs">{r.source}</Td>
                <Td><PublishToggle id={r.id} isPublished={r.isPublished} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      ) : <EmptyState />}
    </AdminPage>
  );
}
