import { prisma } from "@/lib/prisma";

export async function getHomeBlocks() {
  const [slides, hits, features, partners, platforms, reviews] = await Promise.all([
    prisma.slide.findMany({ where: { isActive: true, placement: "HERO" }, orderBy: { sortOrder: "asc" } }),
    prisma.slide.findMany({ where: { isActive: true, placement: "HIT" }, orderBy: { sortOrder: "asc" } }),
    prisma.feature.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.partner.findMany({ where: { isActive: true }, orderBy: { sortOrder: "asc" } }),
    prisma.reviewPlatform.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.review.findMany({ where: { isPublished: true }, orderBy: { publishedAt: "desc" }, take: 8 }),
  ]);
  return { slides, hits, features, partners, platforms, reviews };
}

export async function getReviewsBlock() {
  const [platforms, reviews] = await Promise.all([
    prisma.reviewPlatform.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.review.findMany({ where: { isPublished: true }, orderBy: { publishedAt: "desc" }, take: 8 }),
  ]);
  return { platforms, reviews };
}
