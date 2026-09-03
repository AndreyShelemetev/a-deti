import type { Metadata } from "next";
import { Handshake, Percent, Headset, FileCheck } from "lucide-react";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";
import { AgencyForm } from "@/components/forms/agency-form";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("agentstvam");
}

const PERKS = [
  { icon: Percent, title: "Агентская комиссия", text: "Прозрачные условия и выплаты по каждому забронированному туру." },
  { icon: FileCheck, title: "Полный пакет документов", text: "Договоры, памятки, списки документов для родителей — всё готово." },
  { icon: Headset, title: "Поддержка менеджера", text: "Ответы по наличию мест и условиям в течение рабочего дня." },
  { icon: Handshake, title: "Прямые контракты", text: "Работаем напрямую с лагерями и центрами, в том числе с ВДЦ «Орлёнок»." },
];

export default function AgenciesPage() {
  return (
    <StaticPage slug="agentstvam">
      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        {PERKS.map((p) => (
          <div key={p.title} className="card flex gap-4 p-5">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><p.icon className="h-6 w-6" /></div>
            <div><div className="font-extrabold text-ink-900">{p.title}</div><p className="mt-1 text-sm text-ink-600">{p.text}</p></div>
          </div>
        ))}
      </div>
      <section className="card mt-8 p-6 sm:p-8" id="access">
        <h2 className="text-2xl font-extrabold text-ink-900">Доступ к агентской базе</h2>
        <p className="mb-6 mt-1 text-sm text-ink-600">Для туристических агентств. Заполните форму — откроем личный кабинет и пришлём условия сотрудничества.</p>
        <AgencyForm />
      </section>
    </StaticPage>
  );
}
