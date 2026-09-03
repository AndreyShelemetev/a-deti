import { NextResponse } from "next/server";
import { getTourBySlug } from "@/server/queries/tours";

export async function GET(_request: Request, ctx: RouteContext<"/api/v1/tours/[slug]">) {
  const { slug } = await ctx.params;
  const tour = await getTourBySlug(slug);
  if (!tour) return NextResponse.json({ error: "Not found" }, { status: 404 });
  const { searchText: _omit, ...rest } = tour;
  void _omit;
  return NextResponse.json({ ...rest, url: `/tour/${tour.slug}/`, tags: tour.tags.map((t) => t.tag) }, { headers: { "Cache-Control": "public, s-maxage=60, stale-while-revalidate=300" } });
}
