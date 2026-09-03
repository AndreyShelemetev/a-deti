import { Phone } from "lucide-react";
import { telHref } from "./header";
import { CallbackButton, RequestButton } from "@/components/forms/lead-modals";
import type { SiteSettings } from "@/server/queries/settings";

export function HelpCard({ settings, title = "Поможем выбрать" }: { settings: SiteSettings; title?: string }) {
  return (
    <div className="card sticky top-24 overflow-hidden p-6">
      <div className="blob -right-8 -top-8 h-28 w-28 bg-sun-200/70" />
      <div className="relative">
        <div className="text-xl font-extrabold text-ink-900">{title}</div>
        <p className="mt-2 text-sm text-ink-600">Позвоните или оставьте заявку — менеджер ответит на вопросы и подберёт подходящую программу.</p>
        <a href={telHref(settings["contacts.phone1"] ?? "")} className="mt-4 flex items-center gap-2 text-2xl font-black text-ink-900 hover:text-brand-700"><Phone className="h-5 w-5 text-coral-500" />{settings["contacts.phone1"]}</a>
        <div className="mt-1 text-xs text-ink-500">{settings["contacts.hours"]}</div>
        <div className="mt-4 flex flex-col gap-2">
          <RequestButton className="btn-primary w-full">Отправить заявку</RequestButton>
          <CallbackButton className="btn-outline w-full">Заказать звонок</CallbackButton>
        </div>
      </div>
    </div>
  );
}
