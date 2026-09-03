import type { Metadata } from "next";
import Link from "next/link";
import { BookOpen, CreditCard, FileText, Globe, Home, Inbox, LayoutDashboard, LogOut, Map, MessageSquare, Newspaper, Settings, Tags, Tent, Users, Mail } from "lucide-react";
import { requireSession } from "@/lib/auth";
import { logout } from "@/server/actions/auth";
import { prisma } from "@/lib/prisma";

export const metadata: Metadata = { title: { default: "Панель управления", template: "%s — Админка А-тур" }, robots: { index: false, follow: false } };

const NAV = [
  { href: "/admin/", label: "Обзор", icon: LayoutDashboard },
  { href: "/admin/requests/", label: "Заявки", icon: Inbox, badge: true },
  { href: "/admin/tours/", label: "Туры", icon: Tent },
  { href: "/admin/catalog-pages/", label: "Страницы каталога (SEO)", icon: Map },
  { href: "/admin/tags/", label: "Характеристики и регионы", icon: Tags },
  { href: "/admin/posts/?kind=NEWS", label: "Новости", icon: Newspaper },
  { href: "/admin/posts/?kind=ARTICLE", label: "Статьи", icon: BookOpen },
  { href: "/admin/pages/", label: "Текстовые страницы", icon: FileText },
  { href: "/admin/home/", label: "Главная страница", icon: Home },
  { href: "/admin/reviews/", label: "Отзывы", icon: MessageSquare },
  { href: "/admin/payments/", label: "Платежи", icon: CreditCard },
  { href: "/admin/subscribers/", label: "Подписчики", icon: Mail },
  { href: "/admin/settings/", label: "Настройки сайта", icon: Settings },
  { href: "/admin/users/", label: "Пользователи", icon: Users },
];

export default async function AdminLayout({ children }: LayoutProps<"/admin">) {
  const session = await requireSession();
  const newRequests = await prisma.request.count({ where: { status: "NEW" } });
  return (
    <div className="min-h-screen bg-ink-50 lg:grid lg:grid-cols-[260px_1fr]">
      <aside className="border-b border-ink-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-b-0 lg:border-r">
        <div className="flex items-center justify-between px-5 py-4">
          <Link href="/admin/" className="text-lg font-black text-ink-900">А-тур <span className="text-brand-500">admin</span></Link>
          <Link href="/" target="_blank" className="inline-flex items-center gap-1 text-xs font-bold text-ink-500 hover:text-brand-700"><Globe className="h-3.5 w-3.5" /> сайт</Link>
        </div>
        <nav className="no-scrollbar flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible">
          {NAV.map((n) => (
            <Link key={n.href} href={n.href} className="flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold text-ink-700 hover:bg-brand-50 hover:text-brand-700">
              <n.icon className="h-4 w-4 text-ink-400" /> {n.label}
              {n.badge && newRequests ? <span className="ml-auto rounded-full bg-coral-500 px-2 py-0.5 text-[11px] font-black text-white">{newRequests}</span> : null}
            </Link>
          ))}
        </nav>
        <div className="mt-auto border-t border-ink-100 px-5 py-4 text-xs text-ink-500">
          <div className="font-bold text-ink-800">{session.name}</div>
          <div>{session.email} · {session.role === "ADMIN" ? "администратор" : "менеджер"}</div>
          <form action={logout} className="mt-2"><button type="submit" className="inline-flex items-center gap-1 font-bold text-coral-600 hover:underline"><LogOut className="h-3.5 w-3.5" /> Выйти</button></form>
        </div>
      </aside>
      <div className="p-4 sm:p-6 lg:p-8">{children}</div>
    </div>
  );
}
