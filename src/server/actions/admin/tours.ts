"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/prisma";
import { requireSession } from "@/lib/auth";
import { sanitizeRichHtml } from "@/lib/sanitize";
import { stripHtml } from "@/lib/utils";
import type { FormState } from "@/lib/validation";
import { bool, ensureSlug, fail, fd, optInt, optStr, parseJsonArray } from "./_helpers";
import { Prisma } from "@/generated/prisma/client";

const SECTION_KEYS = ["description", "residence", "programm", "payment_smeny", "otpravka_children", "for_parents", "for_contraindications"] as const;
const SECTION_TITLES: Record<string, string> = { description: "Описание", residence: "Проживание", programm: "Программа", payment_smeny: "Стоимость смен", otpravka_children: "Организованная отправка детей", for_parents: "Документы", for_contraindications: "Противопоказания" };

const schema = z.object({
  id: optInt,
  title: z.string().trim().min(3, "Укажите название").max(300),
  slug: z.string().trim().max(160).optional(),
  type: z.enum(["CAMP", "FAMILY", "SPORT", "EXCURSION", "TRANSFER", "YOUTH"]),
  regionId: optInt,
  short: z.string().trim().max(1000).optional().default(""),
  address: z.string().trim().max(300).optional().default(""),
  ageText: z.string().trim().max(60).optional().default(""),
  ageMin: optInt, ageMax: optInt,
  website: optStr,
  priceText: optStr,
  priceMin: optInt,
  cover: optStr,
  seasons: z.array(z.enum(["SPRING", "SUMMER", "AUTUMN", "WINTER"])).optional().default([]),
  seasonText: optStr,
  isBest: bool, isHit: bool, isPublished: bool,
  sortOrder: optInt,
  seoTitle: optStr, metaDescription: optStr, metaKeywords: optStr,
  tags: z.array(z.string()).optional().default([]),
  shifts: z.string().optional(),
  images: z.string().optional(),
  documents: z.string().optional(),
});

const shiftSchema = z.object({ group: z.string().max(200).nullable().optional(), name: z.string().min(1).max(300), price: z.number().int().nullable().optional(), startDate: z.string().nullable().optional(), endDate: z.string().nullable().optional() });
const imageSchema = z.object({ url: z.string().min(1).max(500), alt: z.string().max(300).nullable().optional() });
const docSchema = z.object({ label: z.string().min(1).max(300), url: z.string().min(1).max(500) });

export async function saveTour(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const raw = fd(formData);
  const parsed = schema.safeParse(raw);
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const slug = ensureSlug(d.slug, d.title);
  const clash = await prisma.tour.findFirst({ where: { slug, NOT: d.id ? { id: d.id } : undefined }, select: { id: true } });
  if (clash) return { ok: false, message: "Такой URL (slug) уже занят другим туром", errors: { slug: ["Занят"] } };

  const shifts = parseJsonArray(d.shifts, shiftSchema);
  const images = parseJsonArray(d.images, imageSchema);
  const documents = parseJsonArray(d.documents, docSchema);
  const sections = SECTION_KEYS.map((key, i) => ({ key, title: (raw[`section_title_${key}`] as string) || SECTION_TITLES[key], html: sanitizeRichHtml((raw[`section_${key}`] as string) ?? ""), sortOrder: i })).filter((s) => s.html.length > 0);
  const tagIds = d.tags.map(Number).filter((n) => Number.isInteger(n));
  const [region, tagRows] = await Promise.all([
    d.regionId ? prisma.region.findUnique({ where: { id: d.regionId } }) : null,
    tagIds.length ? prisma.tag.findMany({ where: { id: { in: tagIds } } }) : [],
  ]);
  const prices = shifts.map((s) => s.price).filter((p): p is number => typeof p === "number" && p > 0);
  const priceMin = d.priceMin ?? (prices.length ? Math.min(...prices) : null);
  const searchText = [d.title, d.short, d.address, d.ageText, region?.name ?? "", ...tagRows.map((t) => t.label), ...sections.map((s) => stripHtml(s.html))].join(" ").toLowerCase();

  const data: Prisma.TourUncheckedCreateInput = {
    slug, title: d.title, type: d.type, regionId: d.regionId, short: d.short, address: d.address, ageText: d.ageText, ageMin: d.ageMin, ageMax: d.ageMax,
    website: d.website, priceText: d.priceText, priceMin, cover: d.cover || images[0]?.url || null, seasons: d.seasons, seasonText: d.seasonText,
    isBest: d.isBest, isHit: d.isHit, isPublished: d.isPublished, sortOrder: d.sortOrder ?? 0, seoTitle: d.seoTitle, metaDescription: d.metaDescription, metaKeywords: d.metaKeywords, searchText,
  };

  const id = await prisma.$transaction(async (tx) => {
    const tour = d.id ? await tx.tour.update({ where: { id: d.id }, data }) : await tx.tour.create({ data });
    await tx.tourShift.deleteMany({ where: { tourId: tour.id } });
    await tx.tourImage.deleteMany({ where: { tourId: tour.id } });
    await tx.tourSection.deleteMany({ where: { tourId: tour.id } });
    await tx.tourTag.deleteMany({ where: { tourId: tour.id } });
    await tx.tourDocument.deleteMany({ where: { tourId: tour.id } });
    if (shifts.length) await tx.tourShift.createMany({ data: shifts.map((s, i) => ({ tourId: tour.id, group: s.group || null, name: s.name, price: s.price ?? null, priceText: s.price ? `${s.price.toLocaleString("ru-RU")} ₽` : null, startDate: s.startDate ? new Date(s.startDate) : null, endDate: s.endDate ? new Date(s.endDate) : null, sortOrder: i })) });
    if (images.length) await tx.tourImage.createMany({ data: images.map((im, i) => ({ tourId: tour.id, url: im.url, alt: im.alt || d.title, sortOrder: i })) });
    if (sections.length) await tx.tourSection.createMany({ data: sections.map((s) => ({ tourId: tour.id, ...s })) });
    if (tagRows.length) await tx.tourTag.createMany({ data: tagRows.map((t) => ({ tourId: tour.id, tagId: t.id })) });
    if (documents.length) await tx.tourDocument.createMany({ data: documents.map((doc, i) => ({ tourId: tour.id, label: doc.label, url: doc.url, sortOrder: i })) });
    return tour.id;
  });
  if (!d.id) redirect(`/admin/tours/${id}/?created=1`);
  return { ok: true, message: "Тур сохранён" };
}

export async function deleteTour(id: number) {
  await requireSession();
  await prisma.tour.delete({ where: { id } });
  redirect("/admin/tours/");
}

export async function toggleTourPublished(id: number, isPublished: boolean) {
  await requireSession();
  await prisma.tour.update({ where: { id }, data: { isPublished } });
}
