import { cache } from "react";
import { prisma } from "@/lib/prisma";
import { Prisma } from "@/generated/prisma/client";
import type { Season, TourType } from "@/generated/prisma/enums";

export const tourCardSelect = {
  id: true, slug: true, title: true, short: true, address: true, ageText: true, ageMin: true, ageMax: true,
  priceText: true, priceMin: true, cover: true, type: true, seasons: true, seasonText: true, isBest: true, isHit: true,
  region: { select: { slug: true, name: true, isForeign: true } },
  tags: { select: { tag: { select: { slug: true, label: true } } }, take: 6 },
} satisfies Prisma.TourSelect;

export type TourCard = Prisma.TourGetPayload<{ select: typeof tourCardSelect }>;

export type TourSort = "default" | "price_asc" | "price_desc" | "new";

export type TourListParams = {
  type?: TourType | null;
  regionSlugs?: string[];
  tagSlugs?: string[];
  season?: Season | null;
  age?: number | null;
  q?: string | null;
  page?: number;
  perPage?: number;
  sort?: TourSort;
};

export function buildTourWhere(p: TourListParams): Prisma.TourWhereInput {
  const and: Prisma.TourWhereInput[] = [{ isPublished: true }];
  if (p.type) and.push({ type: p.type });
  if (p.regionSlugs?.length) and.push({ region: { slug: { in: p.regionSlugs } } });
  for (const slug of p.tagSlugs ?? []) and.push({ tags: { some: { tag: { slug } } } });
  if (p.season) and.push({ seasons: { has: p.season } });
  if (p.age) {
    and.push({ OR: [{ ageMin: null }, { ageMin: { lte: p.age } }] });
    and.push({ OR: [{ ageMax: null }, { ageMax: { gte: p.age } }] });
  }
  if (p.q?.trim()) {
    const words = p.q.toLowerCase().trim().split(/\s+/).slice(0, 6);
    for (const w of words) and.push({ searchText: { contains: w } });
  }
  return { AND: and };
}

function orderBy(sort: TourSort | undefined): Prisma.TourOrderByWithRelationInput[] {
  switch (sort) {
    case "price_asc":
      return [{ priceMin: { sort: "asc", nulls: "last" } }, { sortOrder: "asc" }];
    case "price_desc":
      return [{ priceMin: { sort: "desc", nulls: "last" } }, { sortOrder: "asc" }];
    case "new":
      return [{ createdAt: "desc" }];
    default:
      return [{ isBest: "desc" }, { sortOrder: "asc" }, { id: "asc" }];
  }
}

export async function listTours(params: TourListParams) {
  const perPage = Math.min(Math.max(params.perPage ?? 12, 1), 60);
  const page = Math.max(params.page ?? 1, 1);
  const where = buildTourWhere(params);
  const [items, total] = await Promise.all([
    prisma.tour.findMany({ where, select: tourCardSelect, orderBy: orderBy(params.sort), skip: (page - 1) * perPage, take: perPage }),
    prisma.tour.count({ where }),
  ]);
  return { items, total, page, perPage, pages: Math.max(Math.ceil(total / perPage), 1) };
}

export const getTourBySlug = cache(async (slug: string) => {
  return prisma.tour.findFirst({
    where: { slug, isPublished: true },
    include: {
      region: true,
      tags: { include: { tag: true }, orderBy: { tag: { sortOrder: "asc" } } },
      shifts: { orderBy: { sortOrder: "asc" } },
      images: { orderBy: { sortOrder: "asc" } },
      sections: { orderBy: { sortOrder: "asc" } },
      documents: { orderBy: { sortOrder: "asc" } },
      reviews: { where: { isPublished: true }, orderBy: { publishedAt: "desc" }, take: 10 },
    },
  });
});

export type TourFull = NonNullable<Awaited<ReturnType<typeof getTourBySlug>>>;

export async function getRelatedTours(tour: { id: number; type: TourType; regionId: number | null }, take = 4) {
  return prisma.tour.findMany({
    where: { isPublished: true, id: { not: tour.id }, OR: [{ regionId: tour.regionId ?? -1 }, { type: tour.type }] },
    select: tourCardSelect,
    orderBy: [{ isBest: "desc" }, { sortOrder: "asc" }],
    take,
  });
}

export async function getPublishedTourSlugs() {
  return prisma.tour.findMany({ where: { isPublished: true }, select: { slug: true, updatedAt: true } });
}

export const getFilterFacets = cache(async () => {
  const [regions, tags] = await Promise.all([
    prisma.region.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.tag.findMany({ orderBy: { sortOrder: "asc" }, select: { slug: true, label: true } }),
  ]);
  return { regions, tags };
});

export async function getHomeTours() {
  const [bestRussia, bestAbroad, hits] = await Promise.all([
    prisma.tour.findMany({ where: { isPublished: true, isBest: true, region: { isForeign: false } }, select: tourCardSelect, orderBy: { sortOrder: "asc" }, take: 6 }),
    prisma.tour.findMany({ where: { isPublished: true, isBest: true, region: { isForeign: true } }, select: tourCardSelect, orderBy: { sortOrder: "asc" }, take: 6 }),
    prisma.tour.findMany({ where: { isPublished: true, isHit: true }, select: tourCardSelect, orderBy: { sortOrder: "asc" }, take: 6 }),
  ]);
  return { bestRussia, bestAbroad, hits };
}
