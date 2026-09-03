import Link from "next/link";
import { ChevronDown, Mail, MapPin, Phone, Search } from "lucide-react";
import { Logo } from "./logo";
import { MobileMenu } from "./mobile-menu";
import { CallbackButton, RequestButton } from "@/components/forms/lead-modals";
import { footerPagesNav, mainNav } from "@/config/navigation";
import type { SiteSettings } from "@/server/queries/settings";

export function telHref(phone: string) {
  return `tel:${phone.replace(/[^\d+]/g, "")}`;
}

export function Header({ settings }: { settings: SiteSettings }) {
  const phone1 = settings["contacts.phone1"] ?? "";
  const phone2 = settings["contacts.phone2"] ?? "";
  const email = settings["contacts.email"] ?? "";
  return (
    <>
      {/* top bar (scrolls away) */}
      <div className="hidden border-b border-ink-100 bg-brand-50/60 lg:block">
        <div className="container-site flex h-9 items-center justify-between text-xs font-semibold text-ink-600">
          <div className="flex items-center gap-5">
            <span className="inline-flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5 text-brand-500" />{settings["contacts.addressShort"]}</span>
            <a href={`mailto:${email}`} className="inline-flex items-center gap-1.5 hover:text-brand-700"><Mail className="h-3.5 w-3.5 text-brand-500" />{email}</a>
            <span className="inline-flex items-center gap-1.5 text-mint-700">● Работаем пн–пт, 10:00–18:00</span>
          </div>
          <nav aria-label="Информация" className="flex items-center gap-4">
            {footerPagesNav.slice(0, 5).map((p) => (
              <Link key={p.href} href={p.href} className="hover:text-brand-700">{p.label}</Link>
            ))}
          </nav>
        </div>
      </div>
      {/* main row (sticky) */}
      <header className="sticky top-0 z-40 bg-white/90 shadow-[0_1px_0_0_rgb(20_22_58/0.06)] backdrop-blur-md">
      <div className="container-site flex h-[72px] items-center gap-3 sm:h-20">
        <Logo />
        <nav aria-label="Каталог" className="ml-2 hidden flex-1 items-center gap-0.5 lg:flex xl:ml-4">
          {mainNav.map((item) => (
            <div key={item.href} className="group relative">
              <Link href={item.href} className="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-2 text-[13px] font-extrabold text-ink-800 hover:bg-brand-50 hover:text-brand-700 xl:px-3 xl:text-sm">
                {item.label}
                {item.children?.length ? <ChevronDown className="h-4 w-4 text-ink-400 transition-transform group-hover:rotate-180" /> : null}
              </Link>
              {item.children?.length ? (
                <div className="invisible absolute left-0 top-full z-50 pt-2 opacity-0 transition-all group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                  <ul className="min-w-64 rounded-2xl bg-white p-2 shadow-soft ring-1 ring-ink-100">
                    {item.children.map((c) => (
                      <li key={c.href}><Link href={c.href} className="block rounded-xl px-3 py-2 text-sm font-semibold text-ink-700 hover:bg-brand-50 hover:text-brand-700">{c.label}</Link></li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <Link href="/search/" aria-label="Поиск по сайту" className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-700 hover:bg-brand-100">
            <Search className="h-5 w-5" />
          </Link>
          <div className="hidden flex-col items-end leading-tight md:flex">
            <a href={telHref(phone1)} className="whitespace-nowrap text-base font-extrabold text-ink-900 hover:text-brand-700">{phone1}</a>
            <a href={telHref(phone2)} className="whitespace-nowrap text-xs font-semibold text-ink-500 hover:text-brand-700">{phone2}</a>
          </div>
          <CallbackButton className="btn-outline hidden whitespace-nowrap 2xl:inline-flex">Заказать звонок</CallbackButton>
          <RequestButton className="btn-accent hidden whitespace-nowrap md:inline-flex">Отправить заявку</RequestButton>
          <a href={telHref(phone1)} aria-label="Позвонить" className="flex h-11 w-11 items-center justify-center rounded-full bg-coral-500 text-white md:hidden">
            <Phone className="h-5 w-5" />
          </a>
          <MobileMenu nav={mainNav} pages={footerPagesNav} phone={phone1} phoneHref={telHref(phone1)} />
        </div>
      </div>
      </header>
    </>
  );
}
