import { prisma } from "@/lib/prisma";
import { AdminPage, EmptyState, PAYMENT_STATUS_LABELS, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDate, formatPrice } from "@/lib/utils";
import { PaymentStatusSelect } from "./status-select";

export default async function PaymentsAdmin() {
  const items = await prisma.payment.findMany({ orderBy: { createdAt: "desc" }, take: 200 });
  return (
    <AdminPage title="Платежи" description="Заявки на онлайн-оплату с формы /pay/. После подключения эквайринга статусы будут обновляться автоматически по callback от шлюза.">
      {items.length ? (
        <Table>
          <thead><tr><Th>#</Th><Th>Дата</Th><Th>Договор</Th><Th>Плательщик</Th><Th>Сумма</Th><Th>Статус</Th></tr></thead>
          <tbody>
            {items.map((p) => (
              <tr key={p.id}>
                <Td>{p.id}</Td>
                <Td className="whitespace-nowrap text-xs text-ink-500">{formatDate(p.createdAt, { day: "2-digit", month: "2-digit", year: "2-digit", hour: "2-digit", minute: "2-digit" })}</Td>
                <Td className="font-bold">{p.orderNumber}{p.isMirCard ? <span className="ml-2 chip bg-mint-100 text-mint-700">МИР</span> : null}</Td>
                <Td>{p.fullName}<div className="text-xs text-ink-500">{p.email} {p.phone}</div></Td>
                <Td className="font-bold">{formatPrice(p.amount)}</Td>
                <Td><StatusBadge value={p.status} label={PAYMENT_STATUS_LABELS[p.status]} /><div className="mt-1"><PaymentStatusSelect id={p.id} status={p.status} /></div></Td>
              </tr>
            ))}
          </tbody>
        </Table>
      ) : <EmptyState text="Платежей пока нет" />}
    </AdminPage>
  );
}
