import { prisma } from "@/lib/prisma";
import { tourCardSelect } from "./tours";
import { postListSelect } from "./posts";

export async function searchSite(q: string) {
  const query = q.trim().slice(0, 100);
  if (query.length < 2) return { tours: [], posts: [], pages: [] };
  const words = query.toLowerCase().split(/\s+/).slice(0, 6);
  const [tours, posts, pages] = await Promise.all([
    prisma.tour.findMany({
      where: { isPublished: true, AND: words.map((w) => ({ searchText: { contains: w } })) },
      select: tourCardSelect,
      orderBy: [{ isBest: "desc" }, { sortOrder: "asc" }],
      take: 30,
    }),
    prisma.post.findMany({
      where: { isPublished: true, AND: words.map((w) => ({ OR: [{ title: { contains: w, mode: "insensitive" } }, { html: { contains: w, mode: "insensitive" } }] })) },
      select: postListSelect,
      orderBy: { publishedAt: "desc" },
      take: 20,
    }),
    prisma.page.findMany({
      where: { isPublished: true, AND: words.map((w) => ({ OR: [{ title: { contains: w, mode: "insensitive" } }, { html: { contains: w, mode: "insensitive" } }] })) },
      select: { slug: true, title: true },
      take: 10,
    }),
  ]);
  return { tours, posts, pages };
}
