import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const rubFormatter = new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 });

/** "72 900 ₽" */
export function formatPrice(value: number | null | undefined, currency = "₽") {
  if (value == null) return null;
  return `${rubFormatter.format(value).replace(/[\s\u202f]/g, "\u00a0")}\u00a0${currency}`;
}

export function formatDate(value: Date | string, opts: Intl.DateTimeFormatOptions = { day: "numeric", month: "long", year: "numeric" }) {
  const d = typeof value === "string" ? new Date(value) : value;
  return new Intl.DateTimeFormat("ru-RU", { timeZone: "Europe/Moscow", ...opts }).format(d);
}

export function formatDateShort(value: Date | string) {
  return formatDate(value, { day: "2-digit", month: "2-digit", year: "numeric" });
}

/** Replaces the {year} placeholder used in SEO templates with the current year. */
export function withYear(text: string | null | undefined, year = new Date().getFullYear()) {
  if (!text) return "";
  return text.replace(/\{year\}/g, String(year));
}

/** Russian pluralization: plural(5, ["тур", "тура", "туров"]) → "туров" */
export function plural(n: number, forms: [string, string, string]) {
  const abs = Math.abs(n) % 100;
  const last = abs % 10;
  if (abs > 10 && abs < 20) return forms[2];
  if (last > 1 && last < 5) return forms[1];
  if (last === 1) return forms[0];
  return forms[2];
}

export function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, " ").replace(/&nbsp;/g, " ").replace(/&[a-z]+;/g, " ").replace(/\s+/g, " ").trim();
}

export function truncate(text: string, max = 160) {
  if (text.length <= max) return text;
  const cut = text.slice(0, max);
  const idx = cut.lastIndexOf(" ");
  return `${cut.slice(0, idx > max * 0.5 ? idx : max)}…`;
}

const TRANSLIT: Record<string, string> = {
  а: "a", б: "b", в: "v", г: "g", д: "d", е: "e", ё: "e", ж: "zh", з: "z", и: "i", й: "y", к: "k", л: "l", м: "m", н: "n", о: "o", п: "p",
  р: "r", с: "s", т: "t", у: "u", ф: "f", х: "kh", ц: "ts", ч: "ch", ш: "sh", щ: "shch", ъ: "", ы: "y", ь: "", э: "e", ю: "yu", я: "ya",
};

/** Transliterates Russian text into a URL slug: "Детский лагерь «Вита»" → "detskiy-lager-vita" */
export function slugify(text: string) {
  return text
    .toLowerCase()
    .split("")
    .map((ch) => TRANSLIT[ch] ?? ch)
    .join("")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 120);
}

export function normalizePhone(phone: string) {
  const digits = phone.replace(/\D/g, "");
  if (digits.length === 11 && (digits.startsWith("7") || digits.startsWith("8"))) return `+7${digits.slice(1)}`;
  if (digits.length === 10) return `+7${digits}`;
  return phone.trim();
}

export function absoluteUrl(path: string) {
  const base = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");
  return `${base}${path.startsWith("/") ? path : `/${path}`}`;
}
