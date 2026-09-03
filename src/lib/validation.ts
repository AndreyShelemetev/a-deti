import { z } from "zod";

const phone = z.string().trim().min(6, "Укажите телефон").max(30).regex(/^[+\d][\d\s()\-]+$/, "Некорректный телефон");
const email = z.string().trim().email("Некорректный e-mail").max(120);
const name = z.string().trim().min(2, "Укажите имя").max(120);
const consent = z.literal("on", { message: "Нужно согласие на обработку персональных данных" });
const honeypot = z.string().max(0).optional();

export const requestSchema = z.object({
  name,
  email: email.or(z.literal("")).optional(),
  phone,
  message: z.string().trim().max(3000).optional(),
  consent,
  website: honeypot,
  pageUrl: z.string().max(500).optional(),
});

export const callbackSchema = z.object({
  name,
  phone,
  message: z.string().trim().max(2000).optional(),
  consent,
  website: honeypot,
  pageUrl: z.string().max(500).optional(),
});

export const bookingSchema = z.object({
  name,
  email: email.or(z.literal("")).optional(),
  phone,
  message: z.string().trim().max(3000).optional(),
  tourId: z.coerce.number().int().positive(),
  shiftName: z.string().trim().max(300).optional(),
  amount: z.coerce.number().int().nonnegative().optional(),
  consent,
  website: honeypot,
});

export const subscribeSchema = z.object({
  name: z.string().trim().max(120).optional(),
  email,
  consent,
  website: honeypot,
});

export const agencySchema = z.object({
  name,
  company: z.string().trim().min(2, "Укажите название агентства").max(200),
  phone,
  email,
  message: z.string().trim().max(2000).optional(),
  consent,
  website: honeypot,
});

export const reviewSchema = z.object({
  name,
  text: z.string().trim().min(10, "Напишите хотя бы пару предложений").max(3000),
  rating: z.coerce.number().int().min(1).max(5),
  tourId: z.coerce.number().int().positive().optional(),
  consent,
  website: honeypot,
});

export const paymentSchema = z.object({
  orderNumber: z.string().trim().min(1, "Укажите номер договора").max(50),
  fullName: z.string().trim().min(5, "Укажите ФИО").max(200),
  phone: phone.or(z.literal("")).optional(),
  email,
  amount: z.coerce.number().int().positive("Укажите сумму").max(10_000_000),
  isMirCard: z.enum(["on"]).optional(),
  consent,
  website: honeypot,
});

export const loginSchema = z.object({
  email,
  password: z.string().min(1, "Введите пароль").max(200),
});

export type FormState = { ok: boolean; message?: string; errors?: Record<string, string[]> };

export function zodErrors(error: z.ZodError): Record<string, string[]> {
  const out: Record<string, string[]> = {};
  for (const issue of error.issues) {
    const key = String(issue.path[0] ?? "form");
    (out[key] ??= []).push(issue.message);
  }
  return out;
}
