import Link from "next/link";
import { Mail, MapPin, Phone, Send, ShieldCheck } from "lucide-react";
import { Logo } from "./logo";
import { telHref } from "./header";
import { SubscribeButton } from "@/components/forms/lead-modals";
import { footerCatalogNav, footerPagesNav, footerTypesNav } from "@/config/navigation";
import { withYear } from "@/lib/utils";
import type { SiteSettings } from "@/server/queries/settings";

function VkIcon(props: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={props.className} aria-hidden>
      <path d="M12.8 18.6c-6.2 0-9.7-4.2-9.9-11.3h3.1c.1 5.2 2.4 7.4 4.2 7.8V7.3h2.9v4.5c1.8-.2 3.7-2.2 4.3-4.5h2.9c-.5 2.8-2.5 4.8-4 5.6 1.5.7 3.8 2.4 4.7 5.7h-3.2c-.7-2.2-2.4-3.9-4.7-4.1v4.1h-.3z" />
    </svg>
  );
}

export function Footer({ settings }: { settings: SiteSettings }) {
  const phone1 = settings["contacts.phone1"] ?? "";
  const phone2 = settings["contacts.phone2"] ?? "";
  return (
    <footer className="mt-16 bg-ink-900 text-ink-200">
      <div className="container-site grid gap-10 py-12 md:grid-cols-2 lg:grid-cols-12">
        <div className="lg:col-span-4">
          <div className="inline-block rounded-2xl bg-white p-2"><Logo /></div>
          <p className="mt-4 max-w-sm text-sm leading-relaxed text-ink-300">
            Туроператор детского, молодёжного и семейного отдыха с 2005 года. Лагеря в России и за рубежом, спортивные сборы, экскурсии и трансфер.
          </p>
          <a href={settings["contacts.reestrUrl"]} target="_blank" rel="noopener" className="mt-4 inline-flex items-center gap-2 rounded-2xl bg-ink-800 px-3 py-2 text-xs font-bold text-mint-300 hover:bg-ink-700">
            <ShieldCheck className="h-4 w-4" /> В едином федеральном реестре туроператоров · {settings["contacts.reestr"]}
          </a>
          <div className="mt-5 flex items-center gap-3">
            <span className="text-sm font-bold text-white">Подписывайтесь:</span>
            {settings["social.vk"] ? <a href={settings["social.vk"]} target="_blank" rel="noopener" aria-label="ВКонтакте" className="flex h-10 w-10 items-center justify-center rounded-full bg-ink-800 text-white hover:bg-brand-500"><VkIcon className="h-5 w-5" /></a> : null}
            {settings["social.telegram"] ? <a href={settings["social.telegram"]} target="_blank" rel="noopener" aria-label="Telegram" className="flex h-10 w-10 items-center justify-center rounded-full bg-ink-800 text-white hover:bg-brand-500"><Send className="h-4 w-4" /></a> : null}
            <SubscribeButton className="btn-sun !py-2 !text-xs">Подписка на новости</SubscribeButton>
          </div>
        </div>
        <div className="lg:col-span-2">
          <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-white">Каталог</h3>
          <ul className="space-y-2 text-sm">
            {footerCatalogNav.map((i) => <li key={i.href}><Link href={i.href} className="hover:text-white">{i.label}</Link></li>)}
          </ul>
        </div>
        <div className="lg:col-span-2">
          <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-white">Отдых</h3>
          <ul className="space-y-2 text-sm">
            {footerTypesNav.map((i) => <li key={i.href}><Link href={i.href} className="hover:text-white">{i.label}</Link></li>)}
          </ul>
          <h3 className="mb-3 mt-6 text-sm font-extrabold uppercase tracking-wide text-white">Информация</h3>
          <ul className="space-y-2 text-sm">
            {footerPagesNav.map((i) => <li key={i.href}><Link href={i.href} className="hover:text-white">{i.label}</Link></li>)}
          </ul>
        </div>
        <div className="lg:col-span-4">
          <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-white">Контакты</h3>
          <ul className="space-y-3 text-sm">
            <li><a href={telHref(phone1)} className="inline-flex items-center gap-2 text-lg font-extrabold text-white hover:text-sun-300"><Phone className="h-4 w-4 text-sun-300" />{phone1}</a></li>
            <li><a href={telHref(phone2)} className="inline-flex items-center gap-2 font-bold text-white hover:text-sun-300"><Phone className="h-4 w-4 text-sun-300" />{phone2}</a></li>
            <li><a href={`mailto:${settings["contacts.email"]}`} className="inline-flex items-center gap-2 hover:text-white"><Mail className="h-4 w-4 text-sun-300" />{settings["contacts.email"]}</a></li>
            <li className="flex items-start gap-2"><MapPin className="mt-0.5 h-4 w-4 shrink-0 text-sun-300" /><span>{settings["contacts.address"]}<br /><span className="text-ink-400">{settings["contacts.hours"]}</span></span></li>
          </ul>
        </div>
      </div>
      <div className="border-t border-ink-800">
        <div className="container-site flex flex-col gap-2 py-5 text-xs text-ink-400 sm:flex-row sm:items-center sm:justify-between">
          <span>{withYear(settings["copyright"])} {settings["site.legalName"]} · ИНН {settings["contacts.inn"]} · ОГРН {settings["contacts.ogrn"]}</span>
          <span className="flex gap-4">
            <Link href="/politiks/" className="hover:text-white">Политика конфиденциальности</Link>
            <Link href="/personal-data/" className="hover:text-white">Согласие на обработку</Link>
          </span>
        </div>
      </div>
    </footer>
  );
}
