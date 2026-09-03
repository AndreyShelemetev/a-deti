"use client";

import { AdminForm, F, inputCls } from "@/components/admin/form";
import { saveSettings } from "@/server/actions/admin/misc";

const GROUPS: { title: string; fields: [string, string, string?][] }[] = [
  { title: "Контакты", fields: [["contacts.phone1", "Телефон 1"], ["contacts.phone2", "Телефон 2"], ["contacts.email", "E-mail"], ["contacts.addressShort", "Адрес (кратко, шапка)"], ["contacts.address", "Адрес (полный)"], ["contacts.hours", "Режим работы"], ["contacts.mapLat", "Широта (карта)"], ["contacts.mapLng", "Долгота (карта)"]] },
  { title: "Реквизиты", fields: [["site.name", "Название"], ["site.legalName", "Юридическое лицо"], ["contacts.inn", "ИНН"], ["contacts.ogrn", "ОГРН"], ["contacts.reestr", "Номер в реестре туроператоров"], ["contacts.reestrUrl", "Ссылка на реестр"], ["copyright", "Копирайт", "{year} подставит текущий год"]] },
  { title: "Соцсети", fields: [["social.vk", "ВКонтакте"], ["social.telegram", "Telegram"]] },
  { title: "Главная страница и SEO", fields: [["home.seoTitle", "Title главной"], ["home.metaDescription", "Description главной"], ["home.metaKeywords", "Keywords главной"], ["home.h1", "Скрытый H1 (для поиска)"], ["home.ticketText", "Текст баннера «Авиа и ж/д»"], ["reviews.intro", "Подпись к блоку отзывов"]] },
];

export function SettingsForm({ settings }: { settings: Record<string, string> }) {
  return (
    <AdminForm action={saveSettings}>
      <div className="grid gap-8 xl:grid-cols-2">
        {GROUPS.map((g) => (
          <section key={g.title}>
            <h2 className="mb-3 text-lg font-extrabold">{g.title}</h2>
            <div className="space-y-3">
              {g.fields.map(([key, label, hint]) => (
                <F key={key} label={label} hint={hint}>{key.includes("Description") || key.includes("intro") ? <textarea name={`s:${key}`} defaultValue={settings[key] ?? ""} rows={2} className={inputCls} /> : <input name={`s:${key}`} defaultValue={settings[key] ?? ""} className={inputCls} />}</F>
              ))}
            </div>
          </section>
        ))}
      </div>
    </AdminForm>
  );
}
