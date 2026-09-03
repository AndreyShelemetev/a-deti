import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage, Panel, REQUEST_KIND_LABELS, REQUEST_STATUS_LABELS } from "@/components/admin/ui";
import { AdminForm, DangerAction, F, selectCls } from "@/components/admin/form";
import { deleteRequest, updateRequest } from "@/server/actions/admin/requests";
import { formatDate, formatPrice } from "@/lib/utils";

export default async function RequestDetail(props: PageProps<"/admin/requests/[id]">) {
  const { id } = await props.params;
  const r = await prisma.request.findUnique({ where: { id: Number(id) }, include: { tour: { select: { slug: true, title: true } } } });
  if (!r) notFound();
  const rows: [string, React.ReactNode][] = [
    ["Тип", REQUEST_KIND_LABELS[r.kind]],
    ["Дата", formatDate(r.createdAt, { day: "2-digit", month: "long", year: "numeric", hour: "2-digit", minute: "2-digit" })],
    ["Имя", r.name],
    ["Телефон", r.phone ? <a href={`tel:${r.phone}`} className="font-bold text-brand-600">{r.phone}</a> : "—"],
    ["E-mail", r.email ? <a href={`mailto:${r.email}`} className="font-bold text-brand-600">{r.email}</a> : "—"],
    ["Тур", r.tour ? <Link href={`/tour/${r.tour.slug}/`} target="_blank" className="font-bold text-brand-600">{r.tour.title}</Link> : r.tourTitle ?? "—"],
    ["Смена", r.shiftName ?? "—"],
    ["Сумма", r.amount ? formatPrice(r.amount) : "—"],
    ["Страница", r.pageUrl ?? "—"],
    ["Сообщение", r.message ? <span className="whitespace-pre-wrap">{r.message}</span> : "—"],
    ["Доп. данные", r.payload ? <code className="text-xs">{JSON.stringify(r.payload)}</code> : "—"],
  ];
  const remove = deleteRequest.bind(null, r.id);
  return (
    <AdminPage title={`Заявка #${r.id}`} actions={<Link href="/admin/requests/" className="btn-ghost !py-2 !text-xs">← К списку</Link>}>
      <div className="grid gap-6 lg:grid-cols-12">
        <Panel className="lg:col-span-7">
          <dl className="divide-y divide-ink-100 text-sm">{rows.map(([k, v]) => <div key={k} className="grid gap-1 py-2.5 sm:grid-cols-4"><dt className="font-bold text-ink-500">{k}</dt><dd className="sm:col-span-3">{v ?? "—"}</dd></div>)}</dl>
        </Panel>
        <Panel className="lg:col-span-5" title="Обработка">
          <AdminForm action={updateRequest}>
            <input type="hidden" name="id" value={r.id} />
            <F label="Статус" name="status">
              <select name="status" defaultValue={r.status} className={selectCls}>{Object.entries(REQUEST_STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
            </F>
            <F label="Комментарий менеджера" name="note"><textarea name="note" defaultValue={r.note ?? ""} rows={5} className="input !py-2.5 !text-sm" /></F>
          </AdminForm>
          <div className="mt-4 border-t border-ink-100 pt-3"><DangerAction action={remove} label="Удалить заявку" /></div>
        </Panel>
      </div>
    </AdminPage>
  );
}
