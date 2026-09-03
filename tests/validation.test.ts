import { describe, expect, it } from "vitest";
import { bookingSchema, callbackSchema, paymentSchema, zodErrors } from "@/lib/validation";

describe("form validation", () => {
  it("accepts a valid callback", () => {
    const r = callbackSchema.safeParse({ name: "Анна", phone: "+7 (903) 000-00-00", consent: "on" });
    expect(r.success).toBe(true);
  });
  it("requires consent and a phone", () => {
    const r = callbackSchema.safeParse({ name: "Анна", phone: "", consent: undefined });
    expect(r.success).toBe(false);
    if (!r.success) {
      const errors = zodErrors(r.error);
      expect(errors.phone?.[0]).toBeTruthy();
      expect(errors.consent?.[0]).toBeTruthy();
    }
  });
  it("rejects honeypot submissions", () => {
    const r = callbackSchema.safeParse({ name: "Бот", phone: "+79030000000", consent: "on", website: "http://spam" });
    expect(r.success).toBe(false);
  });
  it("coerces booking numbers", () => {
    const r = bookingSchema.safeParse({ name: "Иван", phone: "+79030000000", consent: "on", tourId: "12", amount: "72900" });
    expect(r.success).toBe(true);
    if (r.success) expect(r.data.amount).toBe(72900);
  });
  it("validates payments", () => {
    const r = paymentSchema.safeParse({ orderNumber: "12/26", fullName: "Иванова Анна Петровна", email: "a@b.ru", amount: "1000", consent: "on" });
    expect(r.success).toBe(true);
    const bad = paymentSchema.safeParse({ orderNumber: "", fullName: "И", email: "no", amount: "0", consent: "on" });
    expect(bad.success).toBe(false);
  });
});
