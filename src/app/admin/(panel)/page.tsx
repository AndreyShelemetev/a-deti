import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, Panel, REQUEST_KIND_LABELS, REQUEST_STATUS_LABELS, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDate } from "@/lib/utils";
import { getAdminTourFacets } from "@/server/queries/admin-tours";

export default async function Dashboard() {
  const facets = await getAdminTourFacets();
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
      <Panel title="Туры: требуют внимания">
        <div className="flex flex-wrap gap-2">
          {([["past", "смены уже прошли", facets.past, "coral"], ["no_shifts", "без смен", facets.noShifts, "sun"], ["no_price", "без цены", facets.noPrice, "sun"], ["no_cover", "без обложки", facets.noCover, "sun"]] as const).map(([flag, label, n, tone]) => (
            <Link key={flag} href={`/admin/tours/?flag=${flag}`} className={`chip ${n ? (tone === "coral" ? "bg-coral-100 text-coral-700" : "bg-sun-100 text-sun-600") : "bg-mint-100 text-mint-700"}`}>{n} · {label}</Link>
          ))}
          <Link href="/admin/tours/?status=draft" className="chip bg-ink-100 text-ink-600">{facets.draft} · черновиков</Link>
          <Link href="/admin/shifts/" className="chip bg-brand-50 text-brand-700">Календарь смен →</Link>
        </div>
        <p className="mt-3 text-xs text-ink-500">Туры с прошедшими сменами удобно перенести на следующий сезон: в списке туров нажмите «след. сезон» — даты и годы сдвинутся автоматически.</p>
      </Panel>
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
