import type { Metadata } from "next";
import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { formatPrice } from "@/lib/utils";

export const metadata: Metadata = { title: "Заявка на оплату принята", robots: { index: false, follow: false } };

export default async function PaySuccess(props: PageProps<"/pay/success">) {
  const sp = await props.searchParams;
  const id = Number(Array.isArray(sp.id) ? sp.id[0] : sp.id);
  const payment = Number.isFinite(id) ? await prisma.payment.findUnique({ where: { id }, select: { id: true, orderNumber: true, amount: true, status: true } }) : null;
  return (
    <div className="container-site py-16 text-center">
      <div className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-mint-100 text-5xl">✅</div>
      <h1 className="text-3xl font-black text-ink-900">Заявка на оплату принята</h1>
      <p className="mx-auto mt-3 max-w-lg text-ink-600">
        {payment ? <>Договор <b>{payment.orderNumber}</b>, сумма <b>{formatPrice(payment.amount)}</b>. </> : null}
        Платёжный шлюз пока не подключён к сайту: менеджер свяжется с вами и пришлёт ссылку на оплату на указанный e-mail.
      </p>
      <Link href="/" className="btn-primary mt-8">На главную</Link>
    </div>
  );
}
