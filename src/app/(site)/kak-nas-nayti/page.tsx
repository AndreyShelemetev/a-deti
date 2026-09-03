import type { Metadata } from "next";
import { Building2, ShieldCheck } from "lucide-react";
import { staticPageMetadata } from "@/components/layout/static-page";
import { getSettings } from "@/server/queries/settings";
import { getPage } from "@/server/queries/pages";
import { PageShell } from "@/components/layout/page-shell";
import { MapBlock } from "@/components/home/map-block";
import { RichText } from "@/components/ui/rich-text";
import { RequestForm } from "@/components/forms/request-form";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("kak-nas-nayti");
}

export default async function ContactsPage() {
  const [settings, page] = await Promise.all([getSettings(), getPage("kak-nas-nayti")]);
  const rows: [string, string | undefined][] = [
    ["Полное наименование", `Общество с ограниченной ответственностью «А-тур»`],
    ["Сокращённое наименование", settings["site.legalName"]],
    ["ИНН", settings["contacts.inn"]],
    ["ОГРН", settings["contacts.ogrn"]],
    ["Реестровый номер туроператора", settings["contacts.reestr"]],
    ["Адрес", settings["contacts.address"]],
    ["Режим работы", settings["contacts.hours"]],
  ];
  return (
    <PageShell title={page?.title ?? "Как нас найти"} crumbs={[{ name: "Контакты" }]} wide>
      <div className="-mx-4 sm:-mx-6 lg:-mx-8"><MapBlock settings={settings} title={false} /></div>
      <div className="grid gap-6 lg:grid-cols-12">
        <div className="card p-6 sm:p-8 lg:col-span-6">
          <h2 className="flex items-center gap-2 text-xl font-extrabold text-ink-900"><Building2 className="h-5 w-5 text-brand-500" /> Реквизиты</h2>
          <dl className="mt-4 divide-y divide-ink-100 text-sm">
            {rows.map(([k, v]) => <div key={k} className="grid gap-1 py-2.5 sm:grid-cols-5"><dt className="font-bold text-ink-500 sm:col-span-2">{k}</dt><dd className="font-semibold text-ink-900 sm:col-span-3">{v}</dd></div>)}
          </dl>
          <a href={settings["contacts.reestrUrl"]} target="_blank" rel="noopener" className="mt-4 inline-flex items-center gap-2 text-sm font-extrabold text-mint-700 hover:underline"><ShieldCheck className="h-4 w-4" /> Проверить в едином федеральном реестре туроператоров</a>
          {page?.html ? <div className="mt-6"><RichText html={page.html} /></div> : null}
        </div>
        <div className="card p-6 sm:p-8 lg:col-span-6">
          <h2 className="text-xl font-extrabold text-ink-900">Напишите нам</h2>
          <p className="mb-5 mt-1 text-sm text-ink-600">Ответим в течение рабочего дня.</p>
          <RequestForm pageUrl="/kak-nas-nayti/" compact />
        </div>
      </div>
    </PageShell>
  );
}
