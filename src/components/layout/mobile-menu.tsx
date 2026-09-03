"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronDown, Menu, Phone, X } from "lucide-react";
import type { NavItem } from "@/config/navigation";

type Props = { nav: NavItem[]; pages: NavItem[]; phone: string; phoneHref: string };

export function MobileMenu({ nav, pages, phone, phoneHref }: Props) {
  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState<string | null>(null);
  const close = () => setOpen(false);
  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [open]);

  return (
    <div className="lg:hidden">
      <button type="button" onClick={() => setOpen(true)} aria-label="Открыть меню" aria-expanded={open} className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-700">
        <Menu className="h-6 w-6" />
      </button>
      {open ? (
        <div className="fixed inset-0 z-50 flex">
          <button type="button" aria-label="Закрыть меню" onClick={() => setOpen(false)} className="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" />
          <div className="relative ml-auto flex h-full w-[88%] max-w-sm flex-col overflow-y-auto bg-white shadow-soft animate-pop">
            <div className="flex items-center justify-between border-b border-ink-100 px-5 py-4">
              <span className="text-lg font-extrabold text-ink-900">Меню</span>
              <button type="button" onClick={() => setOpen(false)} aria-label="Закрыть" className="rounded-full p-2 hover:bg-ink-100"><X className="h-6 w-6" /></button>
            </div>
            <nav className="flex-1 px-3 py-3">
              <ul className="space-y-1">
                {nav.map((item) => (
                  <li key={item.href}>
                    <div className="flex items-center">
                      <Link href={item.href} onClick={close} className="flex-1 rounded-xl px-3 py-3 text-base font-extrabold text-ink-900 hover:bg-brand-50">{item.label}</Link>
                      {item.children?.length ? (
                        <button type="button" aria-label="Развернуть" aria-expanded={expanded === item.href} onClick={() => setExpanded(expanded === item.href ? null : item.href)} className="rounded-xl p-3 text-ink-500 hover:bg-brand-50">
                          <ChevronDown className={`h-5 w-5 transition-transform ${expanded === item.href ? "rotate-180" : ""}`} />
                        </button>
                      ) : null}
                    </div>
                    {item.children?.length && expanded === item.href ? (
                      <ul className="mb-2 ml-3 space-y-0.5 border-l-2 border-brand-100 pl-3">
                        {item.children.map((c) => (
                          <li key={c.href}><Link href={c.href} onClick={close} className="block rounded-lg px-3 py-2 text-sm font-semibold text-ink-700 hover:bg-brand-50">{c.label}</Link></li>
                        ))}
                      </ul>
                    ) : null}
                  </li>
                ))}
              </ul>
              <div className="mt-4 border-t border-ink-100 pt-4">
                <ul className="grid grid-cols-2 gap-1">
                  {pages.map((p) => (
                    <li key={p.href}><Link href={p.href} onClick={close} className="block rounded-lg px-3 py-2 text-sm font-semibold text-ink-600 hover:bg-brand-50">{p.label}</Link></li>
                  ))}
                </ul>
              </div>
            </nav>
            <div className="border-t border-ink-100 p-4">
              <a href={phoneHref} className="btn-primary w-full"><Phone className="h-4 w-4" /> {phone}</a>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
