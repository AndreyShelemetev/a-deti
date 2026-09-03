import { cache } from "react";
import { prisma } from "@/lib/prisma";
import type { PostKind } from "@/generated/prisma/enums";

export const postListSelect = { id: true, slug: true, title: true, excerpt: true, image: true, publishedAt: true, kind: true } as const;

export async function listPosts(kind: PostKind, page = 1, perPage = 9) {
  const where = { kind, isPublished: true };
  const [items, total] = await Promise.all([
    prisma.post.findMany({ where, select: postListSelect, orderBy: { publishedAt: "desc" }, skip: (page - 1) * perPage, take: perPage }),
    prisma.post.count({ where }),
  ]);
  return { items, total, page, perPage, pages: Math.max(Math.ceil(total / perPage), 1) };
}

export const getPost = cache(async (kind: PostKind, slug: string) => {
  return prisma.post.findFirst({ where: { kind, slug, isPublished: true } });
});

export async function getLatestPosts(kind: PostKind, take = 3) {
  return prisma.post.findMany({ where: { kind, isPublished: true }, select: postListSelect, orderBy: { publishedAt: "desc" }, take });
}

export async function getPublishedPostSlugs() {
  return prisma.post.findMany({ where: { isPublished: true }, select: { kind: true, slug: true, updatedAt: true } });
}
