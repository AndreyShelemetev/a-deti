import { prisma } from "@/lib/prisma";
import { Prisma } from "@/generated/prisma/client";
import type { Season, TourType } from "@/generated/prisma/enums";

export type TourStatusFilter = "active" | "published" | "draft" | "archived" | "all";
export type AdminTourFilters = {
  q?: string;
  type?: TourType | null;
  region?: string | null;
  season?: Season | null;
  tag?: string | null;
  status?: TourStatusFilter;
  from?: string | null; // shifts starting on/after (ISO date)
  to?: string | null;   // shifts starting on/before
  priceMin?: number | null;
  priceMax?: number | null;
  flag?: "best" | "hit" | "no_cover" | "no_shifts" | "no_price" | "past" | null;
  sort?: "updated" | "title" | "price" | "order" | "next_shift";
  page?: number;
  perPage?: number;
};

export function buildAdminTourWhere(f: AdminTourFilters): Prisma.TourWhereInput {
  const and: Prisma.TourWhereInput[] = [];
  const now = new Date();
  switch (f.status ?? "active") {
    case "active": and.push({ archivedAt: null }); break;
    case "published": and.push({ archivedAt: null, isPublished: true }); break;
    case "draft": and.push({ archivedAt: null, isPublished: false }); break;
    case "archived": and.push({ archivedAt: { not: null } }); break;
    default: break;
  }
  if (f.q?.trim()) {
    const q = f.q.trim();
    and.push({ OR: [{ title: { contains: q, mode: "insensitive" } }, { slug: { contains: q, mode: "insensitive" } }, { address: { contains: q, mode: "insensitive" } }] });
  }
  if (f.type) and.push({ type: f.type });
  if (f.region) and.push(f.region === "foreign" ? { region: { isForeign: true } } : f.region === "russia_all" ? { region: { isForeign: false } } : { region: { slug: f.region } });
  if (f.season) and.push({ seasons: { has: f.season } });
  if (f.tag) and.push({ tags: { some: { tag: { slug: f.tag } } } });
  if (f.from || f.to) {
    and.push({ shifts: { some: { startDate: { ...(f.from ? { gte: new Date(f.from) } : {}), ...(f.to ? { lte: new Date(`${f.to}T23:59:59Z`) } : {}) } } } });
  }
  if (f.priceMin != null) and.push({ priceMin: { gte: f.priceMin } });
  if (f.priceMax != null) and.push({ priceMin: { lte: f.priceMax } });
  switch (f.flag) {
    case "best": and.push({ isBest: true }); break;
    case "hit": and.push({ isHit: true }); break;
    case "no_cover": and.push({ cover: null }); break;
    case "no_shifts": and.push({ shifts: { none: {} } }); break;
    case "no_price": and.push({ priceMin: null }); break;
    case "past": and.push({ shifts: { some: {} }, NOT: { shifts: { some: { OR: [{ endDate: null }, { endDate: { gte: now } }] } } } }); break;
    default: break;
  }
  return and.length ? { AND: and } : {};
}

export const adminTourSelect = {
  id: true, slug: true, title: true, type: true, cover: true, priceMin: true, priceText: true, isBest: true, isHit: true, isPublished: true, archivedAt: true, sortOrder: true, updatedAt: true, seasons: true, seasonText: true, ageText: true,
  region: { select: { slug: true, name: true, isForeign: true } },
  shifts: { select: { id: true, startDate: true, endDate: true, price: true, availability: true }, orderBy: { startDate: "asc" } },
  _count: { select: { images: true, requests: true, sections: true } },
} satisfies Prisma.TourSelect;
export type AdminTourRow = Prisma.TourGetPayload<{ select: typeof adminTourSelect }>;

function orderBy(sort: AdminTourFilters["sort"]): Prisma.TourOrderByWithRelationInput[] {
  switch (sort) {
    case "title": return [{ title: "asc" }];
    case "price": return [{ priceMin: { sort: "asc", nulls: "last" } }];
    case "order": return [{ sortOrder: "asc" }, { id: "asc" }];
    case "updated": return [{ updatedAt: "desc" }];
    default: return [{ isPublished: "desc" }, { sortOrder: "asc" }, { id: "asc" }];
  }
}

export async function listAdminTours(f: AdminTourFilters) {
  const perPage = Math.min(Math.max(f.perPage ?? 25, 5), 100);
  const page = Math.max(f.page ?? 1, 1);
  const where = buildAdminTourWhere(f);
  const [items, total] = await Promise.all([
    prisma.tour.findMany({ where, select: adminTourSelect, orderBy: orderBy(f.sort), skip: (page - 1) * perPage, take: perPage }),
    prisma.tour.count({ where }),
  ]);
  if (f.sort === "next_shift") {
    const now = Date.now();
    const next = (t: AdminTourRow) => t.shifts.find((s) => s.startDate && s.startDate.getTime() >= now)?.startDate?.getTime() ?? Number.MAX_SAFE_INTEGER;
    items.sort((a, b) => next(a) - next(b));
  }
  return { items, total, page, perPage, pages: Math.max(Math.ceil(total / perPage), 1) };
}

/** Counters for the category tabs and the attention block. */
export async function getAdminTourFacets() {
  const now = new Date();
  const base = { archivedAt: null } as const;
  const [byType, published, draft, archived, noCover, noShifts, noPrice, noImages, past] = await Promise.all([
    prisma.tour.groupBy({ by: ["type"], where: base, _count: { _all: true } }),
    prisma.tour.count({ where: { ...base, isPublished: true } }),
    prisma.tour.count({ where: { ...base, isPublished: false } }),
    prisma.tour.count({ where: { archivedAt: { not: null } } }),
    prisma.tour.count({ where: { ...base, cover: null } }),
    prisma.tour.count({ where: { ...base, shifts: { none: {} } } }),
    prisma.tour.count({ where: { ...base, priceMin: null } }),
    prisma.tour.count({ where: { ...base, images: { none: {} } } }),
    prisma.tour.count({ where: { ...base, shifts: { some: {} }, NOT: { shifts: { some: { OR: [{ endDate: null }, { endDate: { gte: now } }] } } } } }),
  ]);
  return {
    byType: Object.fromEntries(byType.map((r) => [r.type, r._count._all])) as Record<string, number>,
    total: byType.reduce((a, r) => a + r._count._all, 0),
    published, draft, archived, noCover, noShifts, noPrice, noImages, past,
  };
}

/** Upcoming shifts across tours, for the calendar view. */
export async function listShifts(f: { month?: string | null; type?: TourType | null; region?: string | null; availability?: string | null; q?: string | null; includePast?: boolean }) {
  const and: Prisma.TourShiftWhereInput[] = [{ tour: { archivedAt: null } }];
  if (f.month) {
    const [y, m] = f.month.split("-").map(Number);
    if (y && m) and.push({ startDate: { gte: new Date(Date.UTC(y, m - 1, 1)), lt: new Date(Date.UTC(y, m, 1)) } });
  } else if (!f.includePast) {
    and.push({ OR: [{ endDate: null }, { endDate: { gte: new Date() } }] });
  }
  if (f.type) and.push({ tour: { type: f.type } });
  if (f.region) and.push({ tour: f.region === "foreign" ? { region: { isForeign: true } } : { region: { slug: f.region } } });
  if (f.availability === "AVAILABLE" || f.availability === "FEW" || f.availability === "SOLD_OUT") and.push({ availability: f.availability });
  if (f.q?.trim()) and.push({ tour: { title: { contains: f.q.trim(), mode: "insensitive" } } });
  return prisma.tourShift.findMany({
    where: { AND: and },
    include: { tour: { select: { id: true, slug: true, title: true, type: true, isPublished: true, region: { select: { name: true } } } } },
    orderBy: [{ startDate: { sort: "asc", nulls: "last" } }, { tourId: "asc" }, { sortOrder: "asc" }],
    take: 500,
  });
}
