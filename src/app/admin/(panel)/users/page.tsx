import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/auth";
import { AdminPage, Panel, Table, Td, Th, StatusBadge } from "@/components/admin/ui";
import { formatDateShort } from "@/lib/utils";
import { UsersEditor } from "./users-editor";

export default async function UsersAdmin() {
  const me = await requireAdmin();
  const users = await prisma.user.findMany({ orderBy: { id: "asc" }, select: { id: true, email: true, name: true, role: true, isActive: true, createdAt: true } });
  return (
    <AdminPage title="Пользователи" description="Администраторы управляют всем, менеджеры — контентом и заявками (без доступа к пользователям).">
      <Table>
        <thead><tr><Th>Имя</Th><Th>E-mail</Th><Th>Роль</Th><Th>Создан</Th><Th>Статус</Th></tr></thead>
        <tbody>{users.map((u) => <tr key={u.id}><Td className="font-bold">{u.name}{u.id === me.id ? " (вы)" : ""}</Td><Td>{u.email}</Td><Td>{u.role === "ADMIN" ? "Администратор" : "Менеджер"}</Td><Td className="text-xs text-ink-500">{formatDateShort(u.createdAt)}</Td><Td><StatusBadge value={u.isActive} label={u.isActive ? "Активен" : "Отключён"} /></Td></tr>)}</tbody>
      </Table>
      <Panel title="Добавить или изменить"><UsersEditor users={users} meId={me.id} /></Panel>
    </AdminPage>
  );
}
