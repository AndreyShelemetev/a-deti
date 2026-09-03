import { getSettings } from "@/server/queries/settings";
import { AdminPage, Panel } from "@/components/admin/ui";
import { SettingsForm } from "./settings-form";

export default async function SettingsAdmin() {
  const settings = await getSettings();
  return (
    <AdminPage title="Настройки сайта" description="Контакты, реквизиты, соцсети и SEO главной страницы. Изменения применяются сразу.">
      <Panel><SettingsForm settings={settings} /></Panel>
    </AdminPage>
  );
}
