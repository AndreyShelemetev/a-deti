import Link from "next/link";
import { prisma } from "@/lib/prisma";
import type { RequestKind, RequestStatus } from "@/generated/prisma/enums";
import { AdminPage, EmptyState, REQUEST_KIND_LABELS, REQUEST_STATUS_LABELS, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { Pagination } from "@/components/ui/pagination";
import { formatDate } from "@/lib/utils";

const KINDS = Object.keys(REQUEST_KIND_LABELS) as RequestKind[];
const STATUSES = Object.keys(REQUEST_STATUS_LABELS) as RequestStatus[];

export default async function RequestsPage(props: PageProps<"/admin/requests">) {
  const sp = await props.searchParams;
  const one = (k: string) => { const v = sp[k]; return Array.isArray(v) ? v[0] : v; };
  const status = STATUSES.find((s) => s === one("status"));
  const kind = KINDS.find((k) => k === one("kind"));
  const page = Math.max(Number(one("page") ?? 1) || 1, 1);
  const where = { ...(status ? { status } : {}), ...(kind ? { kind } : {}) };
  const [items, total] = await Promise.all([
    prisma.request.findMany({ where, orderBy: { createdAt: "desc" }, skip: (page - 1) * 30, take: 30 }),
    prisma.request.count({ where }),
  ]);
  const qs = (p: number) => { const q = new URLSearchParams(); if (status) q.set("status", status); if (kind) q.set("kind", kind); if (p > 1) q.set("page", String(p)); const s = q.toString(); return `/admin/requests/${s ? `?${s}` : ""}`; };
  return (
    <AdminPage title="Заявки" description={`Всего: ${total}. Заявки с форм сайта: обратный звонок, бронирование, подписка, агентства, отзывы.`}>
      <div className="flex flex-wrap gap-2">
        <Link href="/admin/requests/" className={`chip ${!status && !kind ? "bg-brand-500 text-white" : "bg-white text-ink-700 shadow-card"}`}>Все</Link>
        {STATUSES.map((s) => <Link key={s} href={`/admin/requests/?status=${s}${kind ? `&kind=${kind}` : ""}`} className={`chip ${status === s ? "bg-brand-500 text-white" : "bg-white text-ink-700 shadow-card"}`}>{REQUEST_STATUS_LABELS[s]}</Link>)}
        <span className="mx-1 text-ink-300">|</span>
        {KINDS.map((k) => <Link key={k} href={`/admin/requests/?kind=${k}${status ? `&status=${status}` : ""}`} className={`chip ${kind === k ? "bg-ink-800 text-white" : "bg-white text-ink-700 shadow-card"}`}>{REQUEST_KIND_LABELS[k]}</Link>)}
      </div>
      {items.length ? (
        <Table>
          <thead><tr><Th>#</Th><Th>Дата</Th><Th>Тип</Th><Th>Контакт</Th><Th>Тур / сообщение</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {items.map((r) => (
              <tr key={r.id} className="hover:bg-brand-50/40">
                <Td><Link href={`/admin/requests/${r.id}/`} className="font-bold text-brand-600 hover:underline">#{r.id}</Link></Td>
                <Td className="whitespace-nowrap text-xs text-ink-500">{formatDate(r.createdAt, { day: "2-digit", month: "2-digit", year: "2-digit", hour: "2-digit", minute: "2-digit" })}</Td>
                <Td>{REQUEST_KIND_LABELS[r.kind]}</Td>
                <Td><div className="font-bold">{r.name ?? "—"}</div><div className="text-xs text-ink-500">{r.phone} {r.email}</div></Td>
                <Td className="max-w-md"><div className="line-clamp-2">{r.tourTitle ? <b>{r.tourTitle}</b> : null} {r.shiftName ? <span className="text-ink-500">· {r.shiftName}</span> : null} {r.message}</div></Td>
                <Td><StatusBadge value={r.status} label={REQUEST_STATUS_LABELS[r.status]} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      ) : <EmptyState text="Заявок с такими параметрами нет" />}
      <Pagination page={page} pages={Math.max(Math.ceil(total / 30), 1)} hrefFor={qs} className="!mt-4" />
    </AdminPage>
  );
}
