import { describe, expect, it } from "vitest";
import { hasActiveFilters, parseFilterValues } from "@/components/tour/tour-filters";
import { buildTourWhere } from "@/server/queries/tours";

describe("catalog filters", () => {
  it("parses search params into typed list params", () => {
    const { params, values } = parseFilterValues({ type: "CAMP", region: "crimea", tag: "sea-camps", age: "10", season: "SUMMER", q: "море", sort: "price_asc", page: "2" });
    expect(params).toMatchObject({ type: "CAMP", regionSlugs: ["crimea"], tagSlugs: ["sea-camps"], age: 10, season: "SUMMER", q: "море", sort: "price_asc", page: 2 });
    expect(hasActiveFilters(values)).toBe(true);
  });
  it("ignores unknown values", () => {
    const { params } = parseFilterValues({ type: "HACK", season: "WHENEVER", sort: "random", page: "-3", age: "abc" });
    expect(params.type).toBeNull();
    expect(params.season).toBeNull();
    expect(params.sort).toBe("default");
    expect(params.page).toBe(1);
    expect(params.age).toBeNull();
  });
  it("builds a prisma where clause", () => {
    const where = buildTourWhere({ type: "CAMP", regionSlugs: ["crimea"], tagSlugs: ["sea-camps", "sport-camps"], season: "SUMMER", age: 10, q: "орлёнок море" });
    const and = where.AND as unknown[];
    expect(and[0]).toEqual({ isPublished: true });
    expect(and).toContainEqual({ type: "CAMP" });
    expect(and).toContainEqual({ region: { slug: { in: ["crimea"] } } });
    expect(and).toContainEqual({ tags: { some: { tag: { slug: "sea-camps" } } } });
    expect(and).toContainEqual({ seasons: { has: "SUMMER" } });
    expect(and).toContainEqual({ searchText: { contains: "орлёнок" } });
    expect(and.length).toBe(10);
  });
});
