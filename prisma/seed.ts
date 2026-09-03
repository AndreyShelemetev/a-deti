import "dotenv/config";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import bcrypt from "bcryptjs";
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient } from "../src/generated/prisma/client";
import type { Season, TourType, SlidePlacement, ReviewSource, PostKind } from "../src/generated/prisma/enums";

type SeedShift = { group: string | null; name: string; price: number | null; priceText: string | null; startDate: string | null; endDate: string | null; sortOrder: number };
type SeedSection = { key: string; title: string; html: string; sortOrder: number };
type SeedDoc = { label: string; url: string; sortOrder: number };
type SeedTour = {
  slug: string; legacyId: number | null; title: string; seoTitle: string | null; metaDescription: string | null; metaKeywords: string | null;
  short: string; address: string; ageText: string; ageMin: number | null; ageMax: number | null; website: string | null;
  priceText: string | null; priceMin: number | null; cover: string | null; type: TourType; seasons: Season[]; seasonText: string | null;
  isBest: boolean; isHit: boolean; sortOrder: number; regionSlug: string | null; tagSlugs: string[];
  shifts: SeedShift[]; images: string[]; sections: SeedSection[]; documents: SeedDoc[];
};
type Seed = {
  regions: { slug: string; name: string; isForeign: boolean; sortOrder: number }[];
  tags: { slug: string; label: string; sortOrder: number; h1: string | null; seoTitle: string | null; metaDescription: string | null }[];
  tours: SeedTour[];
  catalogPages: { path: string; h1: string; seoTitle: string | null; metaDescription: string | null; type: TourType | null; regionSlugs: string[]; tagSlugs: string[]; season: Season | null; sortOrder: number }[];
  posts: { kind: PostKind; slug: string; title: string; seoTitle: string | null; metaDescription: string | null; excerpt: string; image: string | null; html: string; publishedAt: string }[];
  pages: { slug: string; title: string; seoTitle: string | null; metaDescription: string | null; metaKeywords?: string | null; html: string }[];
  slides: { placement: SlidePlacement; image: string; href: string | null; title?: string; sortOrder: number }[];
  features: { icon: string | null; text: string; sortOrder: number }[];
  partners: { name: string; logo: string; sortOrder: number }[];
  reviewPlatforms: { name: string; logo: string | null; url: string; rating: number; max: number; sortOrder: number }[];
  reviews: { author: string; text: string; rating: number; source: ReviewSource; sourceUrl: string | null; publishedAt: string }[];
  settings: Record<string, string>;
  redirects: { from: string; to: string }[];
};

const prisma = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, " ").replace(/&nbsp;/g, " ").replace(/&[a-z]+;/g, " ").replace(/\s+/g, " ").trim();
}

async function main() {
  const seed = JSON.parse(readFileSync(join(__dirname, "seed-data", "seed.json"), "utf8")) as Seed;

  // Regions
  const regionIds = new Map<string, number>();
  for (const r of seed.regions) {
    const row = await prisma.region.upsert({ where: { slug: r.slug }, update: { name: r.name, isForeign: r.isForeign, sortOrder: r.sortOrder }, create: r });
    regionIds.set(r.slug, row.id);
  }
  const regionNames = new Map(seed.regions.map((r) => [r.slug, r.name] as const));

  // Tags
  const tagIds = new Map<string, number>();
  for (const t of seed.tags) {
    const row = await prisma.tag.upsert({ where: { slug: t.slug }, update: { label: t.label, sortOrder: t.sortOrder, h1: t.h1, seoTitle: t.seoTitle, metaDescription: t.metaDescription }, create: t });
    tagIds.set(t.slug, row.id);
  }

  // Tours
  for (const t of seed.tours) {
    const tagLabels = t.tagSlugs.map((s) => seed.tags.find((x) => x.slug === s)?.label ?? s);
    const searchText = [t.title, t.short, t.address, t.ageText, regionNames.get(t.regionSlug ?? "") ?? "", ...tagLabels, ...t.sections.map((s) => stripHtml(s.html))].join(" ").toLowerCase();
    const data = {
      legacyId: t.legacyId, title: t.title, seoTitle: t.seoTitle, metaDescription: t.metaDescription, metaKeywords: t.metaKeywords,
      short: t.short, address: t.address, ageText: t.ageText, ageMin: t.ageMin, ageMax: t.ageMax, website: t.website,
      priceText: t.priceText, priceMin: t.priceMin, cover: t.cover, type: t.type, seasons: t.seasons, seasonText: t.seasonText,
      isBest: t.isBest, isHit: t.isHit, sortOrder: t.sortOrder, searchText, isPublished: true,
      regionId: t.regionSlug ? regionIds.get(t.regionSlug) ?? null : null,
    };
    const tour = await prisma.tour.upsert({ where: { slug: t.slug }, update: data, create: { slug: t.slug, ...data } });
    await prisma.$transaction([
      prisma.tourShift.deleteMany({ where: { tourId: tour.id } }),
      prisma.tourImage.deleteMany({ where: { tourId: tour.id } }),
      prisma.tourSection.deleteMany({ where: { tourId: tour.id } }),
      prisma.tourTag.deleteMany({ where: { tourId: tour.id } }),
      prisma.tourDocument.deleteMany({ where: { tourId: tour.id } }),
    ]);
    if (t.shifts.length) await prisma.tourShift.createMany({ data: t.shifts.map((s) => ({ tourId: tour.id, group: s.group, name: s.name, price: s.price, priceText: s.priceText, startDate: s.startDate ? new Date(s.startDate) : null, endDate: s.endDate ? new Date(s.endDate) : null, sortOrder: s.sortOrder })) });
    if (t.images.length) await prisma.tourImage.createMany({ data: t.images.map((url, i) => ({ tourId: tour.id, url, alt: t.title, sortOrder: i })) });
    if (t.sections.length) await prisma.tourSection.createMany({ data: t.sections.map((s) => ({ tourId: tour.id, ...s })) });
    const tagRows = t.tagSlugs.map((s) => tagIds.get(s)).filter((id): id is number => typeof id === "number");
    if (tagRows.length) await prisma.tourTag.createMany({ data: tagRows.map((tagId) => ({ tourId: tour.id, tagId })), skipDuplicates: true });
    if (t.documents.length) await prisma.tourDocument.createMany({ data: t.documents.map((d) => ({ tourId: tour.id, ...d })) });
  }

  // Catalog (SEO) pages
  for (const c of seed.catalogPages) {
    await prisma.catalogPage.upsert({ where: { path: c.path }, update: c, create: c });
  }

  // Posts (news + articles)
  for (const p of seed.posts) {
    const data = { title: p.title, seoTitle: p.seoTitle, metaDescription: p.metaDescription, excerpt: p.excerpt, image: p.image, html: p.html, publishedAt: new Date(p.publishedAt), isPublished: true };
    await prisma.post.upsert({ where: { kind_slug: { kind: p.kind, slug: p.slug } }, update: data, create: { kind: p.kind, slug: p.slug, ...data } });
  }

  // Static pages
  for (const p of seed.pages) {
    const data = { title: p.title, seoTitle: p.seoTitle, metaDescription: p.metaDescription, metaKeywords: p.metaKeywords ?? null, html: p.html, isPublished: true };
    await prisma.page.upsert({ where: { slug: p.slug }, update: data, create: { slug: p.slug, ...data } });
  }

  // Home blocks — replace wholesale
  await prisma.slide.deleteMany();
  await prisma.slide.createMany({ data: seed.slides.map((s) => ({ placement: s.placement, image: s.image, href: s.href, title: s.title ?? null, sortOrder: s.sortOrder })) });
  await prisma.feature.deleteMany();
  await prisma.feature.createMany({ data: seed.features });
  await prisma.partner.deleteMany();
  await prisma.partner.createMany({ data: seed.partners });
  await prisma.reviewPlatform.deleteMany();
  await prisma.reviewPlatform.createMany({ data: seed.reviewPlatforms });
  if ((await prisma.review.count()) === 0) {
    await prisma.review.createMany({ data: seed.reviews.map((r) => ({ ...r, publishedAt: new Date(r.publishedAt) })) });
  }

  // Settings (do not overwrite values edited in admin)
  for (const [key, value] of Object.entries(seed.settings)) {
    await prisma.setting.upsert({ where: { key }, update: {}, create: { key, value } });
  }

  // Legacy redirects
  for (const r of seed.redirects) {
    await prisma.redirect.upsert({ where: { from: r.from }, update: { to: r.to }, create: { from: r.from, to: r.to, statusCode: 301 } });
  }

  // Admin user
  const email = process.env.ADMIN_EMAIL ?? "admin@a-deti.ru";
  const password = process.env.ADMIN_PASSWORD ?? "admin12345";
  await prisma.user.upsert({
    where: { email },
    update: {},
    create: { email, name: "Администратор", role: "ADMIN", passwordHash: bcrypt.hashSync(password, 10) },
  });

  const counts = {
    tours: await prisma.tour.count(), tags: await prisma.tag.count(), regions: await prisma.region.count(), catalogPages: await prisma.catalogPage.count(),
    posts: await prisma.post.count(), pages: await prisma.page.count(), partners: await prisma.partner.count(), reviews: await prisma.review.count(), redirects: await prisma.redirect.count(),
  };
  console.log("Seed complete:", counts);
}

main()
  .catch((e) => { console.error(e); process.exit(1); })
  .finally(() => prisma.$disconnect());
