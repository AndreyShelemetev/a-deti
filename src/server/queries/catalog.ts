import { cache } from "react";
import { prisma } from "@/lib/prisma";

export const getCatalogPage = cache(async (path: string) => {
  return prisma.catalogPage.findUnique({ where: { path } });
});

export async function listCatalogPages() {
  return prisma.catalogPage.findMany({ where: { isIndexable: true }, orderBy: { sortOrder: "asc" } });
}

export const getTagBySlug = cache(async (slug: string) => prisma.tag.findUnique({ where: { slug } }));
