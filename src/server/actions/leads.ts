"use server";

import { prisma } from "@/lib/prisma";
import { notifyLeads } from "@/lib/mail";
import { normalizePhone } from "@/lib/utils";
import {
  agencySchema, bookingSchema, callbackSchema, requestSchema, reviewSchema, subscribeSchema, zodErrors, type FormState,
} from "@/lib/validation";
import type { RequestKind } from "@/generated/prisma/enums";
import type { Prisma } from "@/generated/prisma/client";

const KIND_TITLES: Record<RequestKind, string> = {
  REQUEST: "Заявка с сайта",
  CALLBACK: "Заказ обратного звонка",
  BOOKING: "Бронирование тура",
  SUBSCRIBE: "Подписка на новости",
  AGENCY: "Запрос доступа для агентства",
  REVIEW: "Новый отзыв на модерацию",
};

async function saveLead(kind: RequestKind, data: {
  name?: string | null; email?: string | null; phone?: string | null; message?: string | null;
  tourId?: number | null; tourTitle?: string | null; shiftName?: string | null; amount?: number | null; pageUrl?: string | null; payload?: Prisma.InputJsonObject;
}) {
  const lead = await prisma.request.create({
    data: {
      kind,
      name: data.name ?? null,
      email: data.email || null,
      phone: data.phone ? normalizePhone(data.phone) : null,
      message: data.message || null,
      tourId: data.tourId ?? null,
      tourTitle: data.tourTitle ?? null,
      shiftName: data.shiftName ?? null,
      amount: data.amount ?? null,
      pageUrl: data.pageUrl ?? null,
      payload: data.payload ?? undefined,
    },
  });
  const lines = [
    `${KIND_TITLES[kind]} #${lead.id}`,
    data.name ? `Имя: ${data.name}` : null,
    data.phone ? `Телефон: ${lead.phone}` : null,
    data.email ? `E-mail: ${data.email}` : null,
    data.tourTitle ? `Тур: ${data.tourTitle}` : null,
    data.shiftName ? `Смена: ${data.shiftName}` : null,
    data.amount ? `Сумма: ${data.amount} ₽` : null,
    data.message ? `Сообщение: ${data.message}` : null,
    data.pageUrl ? `Страница: ${data.pageUrl}` : null,
  ].filter(Boolean);
  void notifyLeads({ subject: `${KIND_TITLES[kind]} #${lead.id}`, text: lines.join("\n") });
  return lead;
}

const SUCCESS = "Спасибо! Мы получили вашу заявку и свяжемся с вами в ближайшее время.";

function toObject(formData: FormData) {
  return Object.fromEntries(formData.entries()) as Record<string, string>;
}

export async function submitRequest(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = requestSchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  await saveLead("REQUEST", { name: d.name, email: d.email, phone: d.phone, message: d.message, pageUrl: d.pageUrl });
  return { ok: true, message: SUCCESS };
}

export async function submitCallback(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = callbackSchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  await saveLead("CALLBACK", { name: d.name, phone: d.phone, message: d.message, pageUrl: d.pageUrl });
  return { ok: true, message: "Спасибо! Мы перезвоним вам в рабочее время (пн–пт, 10:00–18:00)." };
}

export async function submitBooking(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = bookingSchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  const tour = await prisma.tour.findUnique({ where: { id: d.tourId }, select: { id: true, title: true, slug: true } });
  if (!tour) return { ok: false, message: "Тур не найден" };
  await saveLead("BOOKING", {
    name: d.name, email: d.email, phone: d.phone, message: d.message,
    tourId: tour.id, tourTitle: tour.title, shiftName: d.shiftName, amount: d.amount, pageUrl: `/tour/${tour.slug}/`,
  });
  return { ok: true, message: "Заявка на бронирование отправлена! Менеджер свяжется с вами для подтверждения места." };
}

export async function submitSubscribe(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = subscribeSchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  await prisma.subscriber.upsert({ where: { email: d.email.toLowerCase() }, update: { name: d.name || undefined, isActive: true }, create: { email: d.email.toLowerCase(), name: d.name || null } });
  await saveLead("SUBSCRIBE", { name: d.name, email: d.email });
  return { ok: true, message: "Вы подписаны на новости А-тур!" };
}

export async function submitAgency(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = agencySchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  await saveLead("AGENCY", { name: d.name, email: d.email, phone: d.phone, message: d.message, payload: { company: d.company } });
  return { ok: true, message: "Спасибо! Мы свяжемся с вами и откроем доступ к агентской базе." };
}

export async function submitReview(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = reviewSchema.safeParse(toObject(formData));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const d = parsed.data;
  await prisma.review.create({ data: { author: d.name, text: d.text, rating: d.rating, source: "SITE", isPublished: false, tourId: d.tourId ?? null } });
  await saveLead("REVIEW", { name: d.name, message: d.text, tourId: d.tourId ?? null, payload: { rating: d.rating } });
  return { ok: true, message: "Спасибо за отзыв! Он появится на сайте после проверки модератором." };
}
