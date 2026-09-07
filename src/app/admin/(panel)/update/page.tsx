import { requireAdmin } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { UPDATER_DEFAULT_REF, updater, updaterConfigured, type UpdaterStatus } from "@/lib/updater";
import { AdminPage, Panel, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { formatDate } from "@/lib/utils";
import { UpdatePanel } from "./update-panel";

const STATUS_LABEL: Record<string, string> = { running: "Выполняется", success: "Успешно", failed: "Ошибка" };
const STATUS_VALUE: Record<string, string> = { running: "IN_PROGRESS", success: "DONE", failed: "FAILED" };

export default async function UpdateAdmin() {
  await requireAdmin();
  const configured = updaterConfigured();
  let status: UpdaterStatus | null = null;
  let error: string | null = null;
  if (configured) {
    try { status = await updater.status(); } catch (e) { error = e instanceof Error ? e.message : "Сервис обновления недоступен"; }
  }
  const history = await prisma.deployment.findMany({ orderBy: { startedAt: "desc" }, take: 20 });
  const buildCommit = process.env.NEXT_PUBLIC_APP_COMMIT || null;

  return (
    <AdminPage title="Обновление сайта" description="Загрузка новой версии из git: сервис-обновлятор скачивает выбранную ветку или тег, собирает сайт и перезапускает его. База данных и загруженные файлы сохраняются, миграции применяются автоматически.">
      {!configured ? (
        <Panel title="Сервис обновления не настроен">
          <p className="text-sm text-ink-600">Задайте переменные окружения <code>UPDATER_URL</code> и <code>UPDATER_TOKEN</code> и запустите сервис <code>updater</code> (в docker-compose он поднимается автоматически). Подробности в <code>updater/README.md</code>.</p>
        </Panel>
      ) : (
        <UpdatePanel initialStatus={status} initialError={error} defaultRef={UPDATER_DEFAULT_REF} buildCommit={buildCommit} runningDeployment={history.find((h) => h.status === "running") ?? null} />
      )}
      <Panel title="История обновлений" className="!p-0">
        {history.length ? (
          <Table className="shadow-none">
            <thead><tr><Th>#</Th><Th>Когда</Th><Th>Ветка / тег</Th><Th>Коммит</Th><Th>Кто</Th><Th>Статус</Th></tr></thead>
            <tbody>
              {history.map((d) => (
                <tr key={d.id}>
                  <Td>{d.id}</Td>
                  <Td className="whitespace-nowrap text-xs text-ink-500">{formatDate(d.startedAt, { day: "2-digit", month: "2-digit", year: "2-digit", hour: "2-digit", minute: "2-digit" })}{d.finishedAt ? ` → ${formatDate(d.finishedAt, { hour: "2-digit", minute: "2-digit" })}` : ""}</Td>
                  <Td className="font-bold">{d.ref}</Td>
                  <Td className="font-mono text-xs">{d.commit?.slice(0, 7) ?? "—"}</Td>
                  <Td className="text-xs">{d.startedBy ?? "—"}</Td>
                  <Td><StatusBadge value={STATUS_VALUE[d.status] ?? d.status} label={STATUS_LABEL[d.status] ?? d.status} /></Td>
                </tr>
              ))}
            </tbody>
          </Table>
        ) : <p className="p-5 text-sm text-ink-500">Обновлений из админки ещё не было.</p>}
      </Panel>
    </AdminPage>
  );
}
