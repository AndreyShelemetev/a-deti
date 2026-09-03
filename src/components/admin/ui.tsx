import Link from "next/link";
import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

export function AdminPage({ title, actions, children, description }: { title: ReactNode; actions?: ReactNode; children: ReactNode; description?: ReactNode }) {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black text-ink-900 sm:text-3xl">{title}</h1>
          {description ? <p className="mt-1 text-sm text-ink-500">{description}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </div>
      {children}
    </div>
  );
}

export function Panel({ children, className, title }: { children: ReactNode; className?: string; title?: ReactNode }) {
  return (
    <section className={cn("rounded-2xl bg-white p-5 shadow-card", className)}>
      {title ? <h2 className="mb-4 text-lg font-extrabold text-ink-900">{title}</h2> : null}
      {children}
    </section>
  );
}

export function Table({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn("overflow-x-auto rounded-2xl bg-white shadow-card", className)}>
      <table className="w-full min-w-[640px] text-sm">{children}</table>
    </div>
  );
}

export function Th({ children, className }: { children?: ReactNode; className?: string }) {
  return <th className={cn("bg-ink-50 px-4 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-ink-500", className)}>{children}</th>;
}

export function Td({ children, className }: { children?: ReactNode; className?: string }) {
  return <td className={cn("border-t border-ink-100 px-4 py-3 align-top text-ink-800", className)}>{children}</td>;
}

const STATUS: Record<string, string> = {
  NEW: "bg-coral-100 text-coral-700", IN_PROGRESS: "bg-sun-100 text-sun-600", DONE: "bg-mint-100 text-mint-700", SPAM: "bg-ink-100 text-ink-500",
  REDIRECTED: "bg-sun-100 text-sun-600", PAID: "bg-mint-100 text-mint-700", FAILED: "bg-coral-100 text-coral-700", CANCELLED: "bg-ink-100 text-ink-500",
  true: "bg-mint-100 text-mint-700", false: "bg-ink-100 text-ink-500",
};

export function StatusBadge({ value, label }: { value: string | boolean; label?: string }) {
  return <span className={cn("chip", STATUS[String(value)] ?? "bg-ink-100 text-ink-600")}>{label ?? String(value)}</span>;
}

export function EmptyState({ text = "Пока ничего нет" }: { text?: string }) {
  return <div className="rounded-2xl bg-white p-10 text-center text-ink-500 shadow-card">{text}</div>;
}

export function LinkButton({ href, children, variant = "primary" }: { href: string; children: ReactNode; variant?: "primary" | "outline" | "ghost" }) {
  const cls = variant === "primary" ? "btn-primary !py-2 !text-xs" : variant === "outline" ? "btn-outline !py-2 !text-xs" : "btn-ghost !py-2 !text-xs";
  return <Link href={href} className={cls}>{children}</Link>;
}

export const REQUEST_KIND_LABELS: Record<string, string> = { REQUEST: "Заявка", CALLBACK: "Звонок", BOOKING: "Бронь", SUBSCRIBE: "Подписка", AGENCY: "Агентство", REVIEW: "Отзыв" };
export const REQUEST_STATUS_LABELS: Record<string, string> = { NEW: "Новая", IN_PROGRESS: "В работе", DONE: "Закрыта", SPAM: "Спам" };
export const PAYMENT_STATUS_LABELS: Record<string, string> = { NEW: "Создан", REDIRECTED: "Отправлен на шлюз", PAID: "Оплачен", FAILED: "Ошибка", CANCELLED: "Отменён" };
