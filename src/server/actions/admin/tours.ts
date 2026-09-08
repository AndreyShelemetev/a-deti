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
  isBest: bool, isHit: bool,
  status: z.enum(["draft", "published", "archived"]).optional().default("draft"),
  internalNote: optStr,
  sortOrder: optInt,
  seoTitle: optStr, metaDescription: optStr, metaKeywords: optStr,
  tags: z.array(z.string()).optional().default([]),
  shifts: z.string().optional(),
  images: z.string().optional(),
  documents: z.string().optional(),
});

const shiftSchema = z.object({
  group: z.string().max(200).nullable().optional(), name: z.string().min(1).max(300), price: z.number().int().nullable().optional(),
  startDate: z.string().nullable().optional(), endDate: z.string().nullable().optional(),
  availability: z.enum(["AVAILABLE", "FEW", "SOLD_OUT"]).optional().default("AVAILABLE"), seatsLeft: z.number().int().nullable().optional(),
});
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
  const priceMin = prices.length ? Math.min(...prices) : d.priceMin;
  const searchText = [d.title, d.short, d.address, d.ageText, region?.name ?? "", ...tagRows.map((t) => t.label), ...sections.map((s) => stripHtml(s.html))].join(" ").toLowerCase();

  const data: Prisma.TourUncheckedCreateInput = {
    slug, title: d.title, type: d.type, regionId: d.regionId, short: d.short, address: d.address, ageText: d.ageText, ageMin: d.ageMin, ageMax: d.ageMax,
    website: d.website, priceText: d.priceText, priceMin, cover: d.cover || images[0]?.url || null, seasons: d.seasons, seasonText: d.seasonText,
    isBest: d.isBest, isHit: d.isHit, isPublished: d.status === "published", archivedAt: d.status === "archived" ? new Date() : null, internalNote: d.internalNote,
    sortOrder: d.sortOrder ?? 0, seoTitle: d.seoTitle, metaDescription: d.metaDescription, metaKeywords: d.metaKeywords, searchText,
  };

  const id = await prisma.$transaction(async (tx) => {
    const tour = d.id ? await tx.tour.update({ where: { id: d.id }, data }) : await tx.tour.create({ data });
    await tx.tourShift.deleteMany({ where: { tourId: tour.id } });
    await tx.tourImage.deleteMany({ where: { tourId: tour.id } });
    await tx.tourSection.deleteMany({ where: { tourId: tour.id } });
    await tx.tourTag.deleteMany({ where: { tourId: tour.id } });
    await tx.tourDocument.deleteMany({ where: { tourId: tour.id } });
    if (shifts.length) await tx.tourShift.createMany({ data: shifts.map((s, i) => ({ tourId: tour.id, group: s.group || null, name: s.name, price: s.price ?? null, priceText: s.price ? `${s.price.toLocaleString("ru-RU")} ₽` : null, startDate: s.startDate ? new Date(s.startDate) : null, endDate: s.endDate ? new Date(s.endDate) : null, availability: s.availability, seatsLeft: s.seatsLeft ?? null, sortOrder: i })) });
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

// ───────── List actions ─────────
export type BulkAction = "publish" | "unpublish" | "archive" | "unarchive" | "best_on" | "best_off" | "hit_on" | "hit_off" | "delete";

export async function bulkTours(ids: number[], action: BulkAction) {
  await requireSession();
  const clean = ids.filter((n) => Number.isInteger(n)).slice(0, 200);
  if (!clean.length) return { ok: false, message: "Ничего не выбрано" };
  const where = { id: { in: clean } };
  switch (action) {
    case "publish": await prisma.tour.updateMany({ where, data: { isPublished: true, archivedAt: null } }); break;
    case "unpublish": await prisma.tour.updateMany({ where, data: { isPublished: false } }); break;
    case "archive": await prisma.tour.updateMany({ where, data: { archivedAt: new Date(), isPublished: false } }); break;
    case "unarchive": await prisma.tour.updateMany({ where, data: { archivedAt: null } }); break;
    case "best_on": await prisma.tour.updateMany({ where, data: { isBest: true } }); break;
    case "best_off": await prisma.tour.updateMany({ where, data: { isBest: false } }); break;
    case "hit_on": await prisma.tour.updateMany({ where, data: { isHit: true } }); break;
    case "hit_off": await prisma.tour.updateMany({ where, data: { isHit: false } }); break;
    case "delete": await prisma.tour.deleteMany({ where }); break;
  }
  return { ok: true, count: clean.length };
}

/** Copies a tour (as a draft). With nextSeason the shift dates move one year ahead and years in names are bumped. */
export async function duplicateTour(id: number, nextSeason: boolean) {
  await requireSession();
  const src = await prisma.tour.findUnique({ where: { id }, include: { shifts: true, images: true, sections: true, tags: true, documents: true } });
  if (!src) return { ok: false as const, message: "Тур не найден" };
  const bump = (text: string) => (nextSeason ? text.replace(/\b(20\d{2})\b/g, (y) => String(Number(y) + 1)) : text);
  const shiftYear = (d: Date | null) => (d && nextSeason ? new Date(Date.UTC(d.getUTCFullYear() + 1, d.getUTCMonth(), d.getUTCDate())) : d);
  const baseSlug = nextSeason ? bump(src.slug) : src.slug;
  let slug = baseSlug === src.slug ? `${src.slug}-copy` : baseSlug;
  for (let i = 2; await prisma.tour.findUnique({ where: { slug } }); i++) slug = `${baseSlug}-${i}`;
  const { id: _id, createdAt: _c, updatedAt: _u, shifts, images, sections, tags, documents, ...rest } = src;
  void _id; void _c; void _u;
  const created = await prisma.tour.create({
    data: {
      ...rest, slug, title: nextSeason ? bump(src.title) : `${src.title} (копия)`, seoTitle: src.seoTitle ? bump(src.seoTitle) : null, seasonText: src.seasonText ? bump(src.seasonText) : null,
      isPublished: false, archivedAt: null, isHit: false,
      shifts: { create: shifts.map((s) => ({ group: s.group, name: bump(s.name), price: s.price, priceText: s.priceText, startDate: shiftYear(s.startDate), endDate: shiftYear(s.endDate), availability: "AVAILABLE", sortOrder: s.sortOrder })) },
      images: { create: images.map((i) => ({ url: i.url, alt: i.alt, sortOrder: i.sortOrder })) },
      sections: { create: sections.map((x) => ({ key: x.key, title: x.title, html: nextSeason ? bump(x.html) : x.html, sortOrder: x.sortOrder })) },
      tags: { create: tags.map((t) => ({ tagId: t.tagId })) },
      documents: { create: documents.map((d) => ({ label: d.label, url: d.url, sortOrder: d.sortOrder })) },
    },
  });
  redirect(`/admin/tours/${created.id}/?copied=1`);
}

export async function setShiftAvailability(id: number, availability: "AVAILABLE" | "FEW" | "SOLD_OUT") {
  await requireSession();
  await prisma.tourShift.update({ where: { id }, data: { availability } });
}
