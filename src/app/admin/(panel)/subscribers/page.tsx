import { prisma } from "@/lib/prisma";
import { AdminPage, EmptyState, Table, Td, Th } from "@/components/admin/ui";
import { formatDateShort } from "@/lib/utils";

export default async function SubscribersAdmin() {
  const items = await prisma.subscriber.findMany({ orderBy: { createdAt: "desc" } });
  return (
    <AdminPage title="Подписчики" description={`${items.length} адресов. Экспорт: выделите таблицу и скопируйте, либо используйте /api при интеграции с рассылкой.`}>
      {items.length ? (
        <Table>
          <thead><tr><Th>E-mail</Th><Th>Имя</Th><Th>Дата</Th><Th>Активен</Th></tr></thead>
          <tbody>{items.map((s) => <tr key={s.id}><Td className="font-bold">{s.email}</Td><Td>{s.name ?? "—"}</Td><Td className="text-xs text-ink-500">{formatDateShort(s.createdAt)}</Td><Td>{s.isActive ? "да" : "нет"}</Td></tr>)}</tbody>
        </Table>
      ) : <EmptyState text="Подписчиков пока нет" />}
    </AdminPage>
  );
}
