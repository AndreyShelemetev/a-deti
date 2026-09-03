import { NextResponse, type NextRequest } from "next/server";
import { listTours } from "@/server/queries/tours";
import { parseFilterValues } from "@/components/tour/tour-filters";

/** Public read-only API (for partners, mobile apps, widgets). GET /api/v1/tours?type=CAMP&region=crimea&age=10&page=1 */
export async function GET(request: NextRequest) {
  const sp = Object.fromEntries(request.nextUrl.searchParams.entries());
  const { params } = parseFilterValues(sp);
  const result = await listTours({ ...params, perPage: Math.min(Number(sp.perPage ?? 20) || 20, 50) });
  return NextResponse.json(
    {
      ...result,
      items: result.items.map((t) => ({ ...t, url: `/tour/${t.slug}/`, tags: t.tags.map((x) => x.tag) })),
    },
    { headers: { "Cache-Control": "public, s-maxage=60, stale-while-revalidate=300" } },
  );
}
