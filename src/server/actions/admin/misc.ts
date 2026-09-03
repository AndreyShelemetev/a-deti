"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/prisma";
import { hashPassword, requireAdmin, requireSession } from "@/lib/auth";
import type { FormState } from "@/lib/validation";
import { bool, fail, fd, optInt, optStr } from "./_helpers";

// ───────── Reviews ─────────
const reviewSchema = z.object({ id: optInt, author: z.string().trim().min(1, "Укажите автора").max(200), text: z.string().trim().min(3, "Укажите текст").max(5000), rating: z.coerce.number().int().min(1).max(5), source: z.enum(["YANDEX", "GOOGLE", "SITE"]), sourceUrl: optStr, publishedAt: z.string().optional(), isPublished: bool, tourId: optInt });
export async function saveReview(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = reviewSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const data = { author: d.author, text: d.text, rating: d.rating, source: d.source, sourceUrl: d.sourceUrl, publishedAt: d.publishedAt ? new Date(d.publishedAt) : new Date(), isPublished: d.isPublished, tourId: d.tourId };
  if (d.id) await prisma.review.update({ where: { id: d.id }, data }); else await prisma.review.create({ data });
  if (!d.id) redirect("/admin/reviews/");
  return { ok: true, message: "Сохранено" };
}
export async function setReviewPublished(id: number, isPublished: boolean) {
  await requireSession();
  await prisma.review.update({ where: { id }, data: { isPublished } });
}
export async function deleteReview(id: number) {
  await requireSession();
  await prisma.review.delete({ where: { id } });
  redirect("/admin/reviews/");
}

// ───────── Home blocks ─────────
const slideSchema = z.object({ id: optInt, placement: z.enum(["HERO", "HIT"]), image: z.string().trim().min(1, "Загрузите изображение"), href: optStr, title: optStr, subtitle: optStr, sortOrder: optInt, isActive: bool });
export async function saveSlide(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = slideSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const { id, ...rest } = parsed.data;
  const data = { ...rest, sortOrder: rest.sortOrder ?? 0 };
  if (id) await prisma.slide.update({ where: { id }, data }); else await prisma.slide.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deleteSlide(id: number) { await requireSession(); await prisma.slide.delete({ where: { id } }); redirect("/admin/home/"); }

const featureSchema = z.object({ id: optInt, icon: optStr, text: z.string().trim().min(2).max(300), sortOrder: optInt });
export async function saveFeature(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = featureSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const { id, ...rest } = parsed.data;
  const data = { ...rest, sortOrder: rest.sortOrder ?? 0 };
  if (id) await prisma.feature.update({ where: { id }, data }); else await prisma.feature.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deleteFeature(id: number) { await requireSession(); await prisma.feature.delete({ where: { id } }); redirect("/admin/home/"); }

const partnerSchema = z.object({ id: optInt, name: z.string().trim().min(1).max(200), logo: z.string().trim().min(1, "Загрузите логотип"), url: optStr, sortOrder: optInt, isActive: bool });
export async function savePartner(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = partnerSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const { id, ...rest } = parsed.data;
  const data = { ...rest, sortOrder: rest.sortOrder ?? 0 };
  if (id) await prisma.partner.update({ where: { id }, data }); else await prisma.partner.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deletePartner(id: number) { await requireSession(); await prisma.partner.delete({ where: { id } }); redirect("/admin/home/"); }

const platformSchema = z.object({ id: optInt, name: z.string().trim().min(1).max(100), logo: optStr, url: z.string().trim().url("Укажите ссылку"), rating: z.coerce.number().min(0).max(5), sortOrder: optInt });
export async function savePlatform(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = platformSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const { id, ...rest } = parsed.data;
  const data = { ...rest, sortOrder: rest.sortOrder ?? 0 };
  if (id) await prisma.reviewPlatform.update({ where: { id }, data }); else await prisma.reviewPlatform.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deletePlatform(id: number) { await requireSession(); await prisma.reviewPlatform.delete({ where: { id } }); redirect("/admin/home/"); }

// ───────── Payments ─────────
export async function setPaymentStatus(id: number, status: "NEW" | "REDIRECTED" | "PAID" | "FAILED" | "CANCELLED") {
  await requireSession();
  await prisma.payment.update({ where: { id }, data: { status } });
}

// ───────── Settings ─────────
export async function saveSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const entries = Object.entries(fd(formData)).filter(([k]) => k.startsWith("s:"));
  await prisma.$transaction(entries.map(([k, v]) => prisma.setting.upsert({ where: { key: k.slice(2) }, update: { value: String(v).trim() }, create: { key: k.slice(2), value: String(v).trim() } })));
  return { ok: true, message: "Настройки сохранены" };
}

// ───────── Users ─────────
const userSchema = z.object({ id: optInt, email: z.string().trim().email("Некорректный e-mail").max(120), name: z.string().trim().min(1, "Укажите имя").max(120), role: z.enum(["ADMIN", "MANAGER"]), password: z.string().max(200).optional(), isActive: bool });
export async function saveUser(_prev: FormState, formData: FormData): Promise<FormState> {
  const me = await requireAdmin();
  const parsed = userSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const email = d.email.toLowerCase();
  if (d.id) {
    if (d.id === me.id && (!d.isActive || d.role !== "ADMIN")) return { ok: false, message: "Нельзя отключить или понизить самого себя" };
    await prisma.user.update({ where: { id: d.id }, data: { email, name: d.name, role: d.role, isActive: d.isActive, ...(d.password ? { passwordHash: await hashPassword(d.password) } : {}) } });
  } else {
    if (!d.password || d.password.length < 8) return { ok: false, message: "Пароль минимум 8 символов", errors: { password: ["Минимум 8 символов"] } };
    if (await prisma.user.findUnique({ where: { email } })) return { ok: false, message: "Пользователь с таким e-mail уже есть" };
    await prisma.user.create({ data: { email, name: d.name, role: d.role, isActive: true, passwordHash: await hashPassword(d.password) } });
  }
  return { ok: true, message: "Сохранено" };
}
export async function deleteUser(id: number) {
  const me = await requireAdmin();
  if (me.id === id) return;
  await prisma.user.delete({ where: { id } });
  redirect("/admin/users/");
}
