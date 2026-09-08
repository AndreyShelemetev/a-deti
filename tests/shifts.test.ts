import { describe, expect, it } from "vitest";
import { addYears, buildShiftName, durationDays, formatShiftRange, parseShiftDates } from "@/lib/shifts";

describe("shift date parsing", () => {
  it("parses numeric ranges with year at the end", () => {
    expect(parseShiftDates("1 смена 01.06-21.06.2026 [21 день]")).toEqual({ startDate: "2026-06-01", endDate: "2026-06-21" });
    expect(parseShiftDates("6 смена (30.05.2026 - 19.06.2026) [21 день]")).toEqual({ startDate: "2026-05-30", endDate: "2026-06-19" });
    expect(parseShiftDates("2 смена (24.06-14.07) 2026 [21 день]")).toEqual({ startDate: "2026-06-24", endDate: "2026-07-14" });
  });
  it("parses russian month names", () => {
    expect(parseShiftDates("01 июня – 21 июня 2026 [21 день]")).toEqual({ startDate: "2026-06-01", endDate: "2026-06-21" });
    expect(parseShiftDates("с 3 по 23 июля 2026")).toEqual({ startDate: "2026-07-03", endDate: "2026-07-23" });
    expect(parseShiftDates("28 декабря – 5 января 2027")).toEqual({ startDate: "2026-12-28", endDate: "2027-01-05" });
  });
  it("returns nulls when there is nothing to parse", () => {
    expect(parseShiftDates("Летняя смена")).toEqual({ startDate: null, endDate: null });
  });
  it("builds names and durations", () => {
    expect(durationDays("2026-06-01", "2026-06-21")).toBe(21);
    expect(buildShiftName(3, "2026-07-13", "2026-08-02")).toBe("3 смена 13.07–02.08.2026 [21 день]");
    expect(formatShiftRange("2026-07-13", "2026-08-02")).toBe("13 июля – 2 августа 2026");
    expect(addYears("2026-06-01", 1)).toBe("2027-06-01");
  });
});
