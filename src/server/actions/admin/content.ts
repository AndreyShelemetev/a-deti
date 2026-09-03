"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/prisma";
import { requireSession } from "@/lib/auth";
import { sanitizeRichHtml } from "@/lib/sanitize";
import { stripHtml, truncate } from "@/lib/utils";
import type { FormState } from "@/lib/validation";
import { bool, ensureSlug, fail, fd, optInt, optStr } from "./_helpers";

// ───────── Posts (news & articles) ─────────
const postSchema = z.object({
  id: optInt,
  kind: z.enum(["NEWS", "ARTICLE"]),
  title: z.string().trim().min(3, "Укажите заголовок").max(300),
  slug: z.string().trim().max(160).optional(),
  excerpt: optStr,
  image: optStr,
  html: z.string().optional().default(""),
  publishedAt: z.string().optional(),
  isPublished: bool,
  seoTitle: optStr, metaDescription: optStr,
});

export async function savePost(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = postSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const slug = ensureSlug(d.slug, d.title);
  const clash = await prisma.post.findFirst({ where: { kind: d.kind, slug, NOT: d.id ? { id: d.id } : undefined } });
  if (clash) return { ok: false, message: "Такой URL уже занят", errors: { slug: ["Занят"] } };
  const html = sanitizeRichHtml(d.html);
  const data = { kind: d.kind, slug, title: d.title, excerpt: d.excerpt ?? truncate(stripHtml(html), 180), image: d.image, html, publishedAt: d.publishedAt ? new Date(d.publishedAt) : new Date(), isPublished: d.isPublished, seoTitle: d.seoTitle, metaDescription: d.metaDescription };
  const row = d.id ? await prisma.post.update({ where: { id: d.id }, data }) : await prisma.post.create({ data });
  if (!d.id) redirect(`/admin/posts/${row.id}/`);
  return { ok: true, message: "Сохранено" };
}

export async function deletePost(id: number) {
  await requireSession();
  const post = await prisma.post.delete({ where: { id } });
  redirect(`/admin/posts/?kind=${post.kind}`);
}

// ───────── Static pages ─────────
const pageSchema = z.object({
  id: optInt,
  slug: z.string().trim().min(1, "Укажите slug").max(120),
  title: z.string().trim().min(2, "Укажите заголовок").max(300),
  html: z.string().optional().default(""),
  isPublished: bool,
  seoTitle: optStr, metaDescription: optStr, metaKeywords: optStr,
});

export async function savePage(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = pageSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const slug = d.slug.replace(/^\/+|\/+$/g, "");
  const clash = await prisma.page.findFirst({ where: { slug, NOT: d.id ? { id: d.id } : undefined } });
  if (clash) return { ok: false, message: "Такой slug уже есть", errors: { slug: ["Занят"] } };
  const data = { slug, title: d.title, html: sanitizeRichHtml(d.html), isPublished: d.isPublished, seoTitle: d.seoTitle, metaDescription: d.metaDescription, metaKeywords: d.metaKeywords };
  const row = d.id ? await prisma.page.update({ where: { id: d.id }, data }) : await prisma.page.create({ data });
  if (!d.id) redirect(`/admin/pages/${row.id}/`);
  return { ok: true, message: "Сохранено" };
}

export async function deletePage(id: number) {
  await requireSession();
  await prisma.page.delete({ where: { id } });
  redirect("/admin/pages/");
}

// ───────── Catalog (SEO) pages ─────────
const catalogSchema = z.object({
  id: optInt,
  path: z.string().trim().min(1, "Укажите путь").max(200).transform((v) => v.replace(/^\/?tour\/filter\//, "").replace(/^\/+|\/+$/g, "")),
  h1: z.string().trim().min(2, "Укажите H1").max(300),
  seoTitle: optStr, metaDescription: optStr,
  seoText: z.string().optional().default(""),
  type: z.enum(["", "CAMP", "FAMILY", "SPORT", "EXCURSION", "TRANSFER", "YOUTH"]).optional(),
  regionSlugs: z.array(z.string()).optional().default([]),
  tagSlugs: z.array(z.string()).optional().default([]),
  season: z.enum(["", "SPRING", "SUMMER", "AUTUMN", "WINTER"]).optional(),
  isIndexable: bool,
  sortOrder: optInt,
});

export async function saveCatalogPage(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = catalogSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const clash = await prisma.catalogPage.findFirst({ where: { path: d.path, NOT: d.id ? { id: d.id } : undefined } });
  if (clash) return { ok: false, message: "Страница с таким путём уже есть", errors: { path: ["Занят"] } };
  const data = { path: d.path, h1: d.h1, seoTitle: d.seoTitle, metaDescription: d.metaDescription, seoText: sanitizeRichHtml(d.seoText) || null, type: d.type || null, regionSlugs: d.regionSlugs, tagSlugs: d.tagSlugs, season: d.season || null, isIndexable: d.isIndexable, sortOrder: d.sortOrder ?? 0 };
  const row = d.id ? await prisma.catalogPage.update({ where: { id: d.id }, data }) : await prisma.catalogPage.create({ data });
  if (!d.id) redirect(`/admin/catalog-pages/${row.id}/`);
  return { ok: true, message: "Сохранено" };
}

export async function deleteCatalogPage(id: number) {
  await requireSession();
  await prisma.catalogPage.delete({ where: { id } });
  redirect("/admin/catalog-pages/");
}

// ───────── Tags & regions ─────────
const tagSchema = z.object({ id: optInt, slug: z.string().trim().max(120).optional(), label: z.string().trim().min(1, "Укажите название").max(120), h1: optStr, seoTitle: optStr, metaDescription: optStr, sortOrder: optInt });
export async function saveTag(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = tagSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const slug = ensureSlug(d.slug, d.label);
  const data = { slug, label: d.label, h1: d.h1, seoTitle: d.seoTitle, metaDescription: d.metaDescription, sortOrder: d.sortOrder ?? 0 };
  if (d.id) await prisma.tag.update({ where: { id: d.id }, data }); else await prisma.tag.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deleteTag(id: number) {
  await requireSession();
  await prisma.tag.delete({ where: { id } });
  redirect("/admin/tags/");
}
const regionSchema = z.object({ id: optInt, slug: z.string().trim().max(120).optional(), name: z.string().trim().min(1, "Укажите название").max(120), isForeign: bool, sortOrder: optInt });
export async function saveRegion(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = regionSchema.safeParse(fd(formData));
  if (!parsed.success) return fail(parsed.error);
  const d = parsed.data;
  const slug = ensureSlug(d.slug, d.name);
  const data = { slug, name: d.name, isForeign: d.isForeign, sortOrder: d.sortOrder ?? 0 };
  if (d.id) await prisma.region.update({ where: { id: d.id }, data }); else await prisma.region.create({ data });
  return { ok: true, message: "Сохранено" };
}
export async function deleteRegion(id: number) {
  await requireSession();
  await prisma.region.delete({ where: { id } });
  redirect("/admin/tags/");
}
