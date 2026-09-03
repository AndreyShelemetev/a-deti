import { cache } from "react";
import { prisma } from "@/lib/prisma";

export type SiteSettings = Record<string, string>;

export const getSettings = cache(async (): Promise<SiteSettings> => {
  const rows = await prisma.setting.findMany();
  return Object.fromEntries(rows.map((r) => [r.key, r.value]));
});

export async function getSetting(key: string, fallback = "") {
  const settings = await getSettings();
  return settings[key] ?? fallback;
}
