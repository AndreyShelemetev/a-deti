import { cache } from "react";
import { prisma } from "@/lib/prisma";

export const getPage = cache(async (slug: string) => {
  return prisma.page.findFirst({ where: { slug, isPublished: true } });
});

export async function getPublishedPageSlugs() {
  return prisma.page.findMany({ where: { isPublished: true }, select: { slug: true, updatedAt: true } });
}
