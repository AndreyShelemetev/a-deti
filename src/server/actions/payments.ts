"use server";

import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { notifyLeads } from "@/lib/mail";
import { normalizePhone } from "@/lib/utils";
import { paymentSchema, zodErrors, type FormState } from "@/lib/validation";

/**
 * Creates a payment intent and hands the visitor to the acquiring gateway.
 * The Sberbank gateway integration is pluggable: when PAYMENT_GATEWAY_URL is not configured,
 * the visitor lands on a confirmation page and managers see the payment in the admin panel.
 */
export async function submitPayment(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = paymentSchema.safeParse(Object.fromEntries(formData.entries()));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  const payment = await prisma.payment.create({
    data: {
      orderNumber: d.orderNumber,
      fullName: d.fullName,
      phone: d.phone ? normalizePhone(d.phone) : null,
      email: d.email.toLowerCase(),
      amount: d.amount,
      isMirCard: d.isMirCard === "on",
    },
  });
  void notifyLeads({
    subject: `Онлайн-оплата #${payment.id}: договор ${d.orderNumber}, ${d.amount} ₽`,
    text: `Плательщик: ${d.fullName}\nДоговор: ${d.orderNumber}\nСумма: ${d.amount} ₽\nE-mail: ${d.email}\nТелефон: ${payment.phone ?? "-"}\nКарта МИР: ${payment.isMirCard ? "да" : "нет"}`,
  });

  const gateway = process.env.PAYMENT_GATEWAY_URL;
  if (gateway) {
    const url = new URL(gateway);
    url.searchParams.set("orderNumber", `${d.orderNumber}-${payment.id}`);
    url.searchParams.set("amount", String(d.amount * 100));
    url.searchParams.set("email", d.email);
    await prisma.payment.update({ where: { id: payment.id }, data: { status: "REDIRECTED", gatewayUrl: url.toString() } });
    redirect(url.toString());
  }
  redirect(`/pay/success/?id=${payment.id}`);
}
