import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, Panel, REQUEST_KIND_LABELS, REQUEST_STATUS_LABELS, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDate } from "@/lib/utils";

export default async function Dashboard() {
  const [newRequests, tours, published, posts, reviewsPending, payments, latest] = await Promise.all([
    prisma.request.count({ where: { status: "NEW" } }),
    prisma.tour.count(),
    prisma.tour.count({ where: { isPublished: true } }),
    prisma.post.count(),
    prisma.review.count({ where: { isPublished: false } }),
    prisma.payment.count({ where: { status: { in: ["NEW", "REDIRECTED"] } } }),
    prisma.request.findMany({ orderBy: { createdAt: "desc" }, take: 8 }),
  ]);
  const cards = [
    { label: "Новые заявки", value: newRequests, href: "/admin/requests/?status=NEW", tone: "bg-coral-100 text-coral-700" },
    { label: "Туры (опубликовано)", value: `${published} / ${tours}`, href: "/admin/tours/", tone: "bg-brand-50 text-brand-700" },
    { label: "Новости и статьи", value: posts, href: "/admin/posts/", tone: "bg-mint-100 text-mint-700" },
    { label: "Отзывы на модерации", value: reviewsPending, href: "/admin/reviews/?published=0", tone: "bg-sun-100 text-sun-600" },
    { label: "Платежи в работе", value: payments, href: "/admin/payments/", tone: "bg-berry-100 text-berry-600" },
  ];
  return (
    <AdminPage title="Обзор" description="Что происходит на сайте прямо сейчас.">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {cards.map((c) => (
          <Link key={c.label} href={c.href} className={`rounded-2xl p-5 shadow-card ${c.tone}`}>
            <div className="text-3xl font-black">{c.value}</div>
            <div className="mt-1 text-sm font-bold opacity-80">{c.label}</div>
          </Link>
        ))}
      </div>
      <Panel title="Последние заявки">
        <Table className="shadow-none">
          <thead><tr><Th>Дата</Th><Th>Тип</Th><Th>Контакт</Th><Th>Тур</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {latest.map((r) => (
              <tr key={r.id}>
                <Td><Link href={`/admin/requests/${r.id}/`} className="font-bold text-brand-600 hover:underline">#{r.id}</Link><div className="text-xs text-ink-400">{formatDate(r.createdAt, { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" })}</div></Td>
                <Td>{REQUEST_KIND_LABELS[r.kind]}</Td>
                <Td>{r.name}<div className="text-xs text-ink-500">{r.phone} {r.email}</div></Td>
                <Td className="max-w-xs"><span className="line-clamp-2">{r.tourTitle ?? r.message ?? "—"}</span></Td>
                <Td><StatusBadge value={r.status} label={REQUEST_STATUS_LABELS[r.status]} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Panel>
    </AdminPage>
  );
}
