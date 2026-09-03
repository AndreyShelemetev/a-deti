import { z } from "zod";
import type { FormState } from "@/lib/validation";
import { zodErrors } from "@/lib/validation";
import { slugify } from "@/lib/utils";

export function fd(formData: FormData) {
  const obj: Record<string, string | string[]> = {};
  for (const [k, v] of formData.entries()) {
    if (typeof v !== "string") continue;
    if (k.endsWith("[]")) { const key = k.slice(0, -2); (obj[key] = (obj[key] as string[]) ?? []).push(v); }
    else obj[k] = v;
  }
  return obj;
}

export const optStr = z.string().trim().max(5000).optional().transform((v) => (v ? v : null));
export const optInt = z.string().trim().optional().transform((v) => (v ? Number.parseInt(v, 10) : null)).pipe(z.number().int().nullable());
export const bool = z.string().optional().transform((v) => v === "on" || v === "true" || v === "1");
export const slugField = z.string().trim().max(160).transform((v) => slugify(v)).pipe(z.string().min(1, "Укажите URL (slug)"));

export function ensureSlug(slug: string | undefined, title: string) {
  const s = slug?.trim() ? slugify(slug) : slugify(title);
  return s || `item-${Date.now()}`;
}

export function fail(error: z.ZodError): FormState {
  return { ok: false, message: "Проверьте выделенные поля", errors: zodErrors(error) };
}

export function parseJsonArray<T>(raw: string | undefined, schema: z.ZodType<T>): T[] {
  if (!raw) return [];
  try {
    const arr = JSON.parse(raw) as unknown;
    if (!Array.isArray(arr)) return [];
    return arr.map((x) => schema.parse(x));
  } catch {
    return [];
  }
}
