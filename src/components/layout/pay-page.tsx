import Image from "next/image";
import { ShieldCheck } from "lucide-react";
import { getPage } from "@/server/queries/pages";
import { getSettings } from "@/server/queries/settings";
import { PageShell } from "@/components/layout/page-shell";
import { HelpCard } from "@/components/layout/help-card";
import { RichText } from "@/components/ui/rich-text";
import { PaymentForm } from "@/components/forms/payment-form";

export async function PayPage({ cashback = false }: { cashback?: boolean }) {
  const [page, settings] = await Promise.all([getPage(cashback ? "pay-cashback" : "pay"), getSettings()]);
  const title = page?.title ?? "Онлайн оплата туров";
  return (
    <PageShell title={title} crumbs={cashback ? [{ name: "Оплата", href: "/pay/" }, { name: "Карта МИР" }] : [{ name: "Оплата" }]} aside={<HelpCard settings={settings} title="Вопросы по оплате?" />}>
      <div className="card p-6 sm:p-8">
        <div className="mb-6 flex flex-wrap items-center gap-3">
          <span className="inline-flex items-center gap-2 rounded-full bg-mint-100 px-3 py-1 text-xs font-extrabold text-mint-700"><ShieldCheck className="h-4 w-4" /> Защищённое соединение · ПАО Сбербанк</span>
          <span className="flex items-center gap-2">
            {(cashback ? ["mir"] : ["mir", "visa", "mastercard"]).map((c) => <Image key={c} src={`/images/pay/${c}.svg`} alt={c} width={48} height={30} className="h-7 w-auto rounded bg-white p-0.5 ring-1 ring-ink-100" unoptimized />)}
          </span>
        </div>
        <h2 className="mb-4 text-xl font-extrabold text-ink-900">Для оплаты введите ваши данные</h2>
        <PaymentForm cashback={cashback} />
      </div>
      {page?.html ? <article className="card mt-6 p-6 text-sm sm:p-8"><RichText html={page.html} className="!text-sm" /></article> : null}
    </PageShell>
  );
}
