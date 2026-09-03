import { describe, expect, it } from "vitest";
import { formatPrice, normalizePhone, plural, slugify, truncate, withYear } from "@/lib/utils";

describe("utils", () => {
  it("formats prices in rubles", () => {
    expect(formatPrice(72900)).toBe("72\u00a0900\u00a0₽");
    expect(formatPrice(null)).toBeNull();
  });
  it("pluralizes russian nouns", () => {
    const forms: [string, string, string] = ["тур", "тура", "туров"];
    expect(plural(1, forms)).toBe("тур");
    expect(plural(3, forms)).toBe("тура");
    expect(plural(11, forms)).toBe("туров");
    expect(plural(43, forms)).toBe("тура");
    expect(plural(100, forms)).toBe("туров");
  });
  it("transliterates slugs", () => {
    expect(slugify("Детский лагерь «Вита» 2026")).toBe("detskiy-lager-vita-2026");
    expect(slugify("  Ж/Д трансфер в ВДЦ Орлёнок ")).toBe("zh-d-transfer-v-vdts-orlenok");
  });
  it("normalizes phones", () => {
    expect(normalizePhone("8 (903) 629-06-21")).toBe("+79036290621");
    expect(normalizePhone("903 629 06 21")).toBe("+79036290621");
  });
  it("substitutes the year placeholder", () => {
    expect(withYear("Детские лагеря в Крыму, {year}", 2027)).toBe("Детские лагеря в Крыму, 2027");
  });
  it("truncates on word boundary", () => {
    expect(truncate("Лагерь на берегу Чёрного моря для детей", 20)).toBe("Лагерь на берегу…");
  });
});
