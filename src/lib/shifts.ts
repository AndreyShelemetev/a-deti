/** Helpers for camp shifts: parsing dates out of free-text names and generating names from dates. */

const RU_MONTHS: Record<string, number> = {
  январ: 1, феврал: 2, март: 3, апрел: 4, ма: 5, мая: 5, июн: 6, июл: 7, август: 8, сентябр: 9, октябр: 10, ноябр: 11, декабр: 12,
};
const MONTH_GEN = ["января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря"];

function monthFromWord(word: string): number | null {
  const w = word.toLowerCase();
  if (w.startsWith("мая") || w === "май") return 5;
  for (const [stem, n] of Object.entries(RU_MONTHS)) if (stem !== "ма" && w.startsWith(stem)) return n;
  return null;
}

function iso(y: number, m: number, d: number) {
  if (m < 1 || m > 12 || d < 1 || d > 31) return null;
  return `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
}

/**
 * Extracts start/end ISO dates from names such as
 *  "1 смена 01.06-21.06.2026 [21 день]", "6 смена (30.05.2026 - 19.06.2026)", "1 смена (01.06-21.06) 2026 [21 день]",
 *  "01 июня – 21 июня 2026 [21 день]", "с 3 по 23 июля 2026".
 */
export function parseShiftDates(name: string): { startDate: string | null; endDate: string | null } {
  const text = name.replace(/\u00a0/g, " ").replace(/\s+по\s+/gi, "–").replace(/^\s*с\s+/i, "");
  const yearMatch = text.match(/\b(20\d{2})\b/g);
  const fallbackYear = yearMatch ? Number(yearMatch[yearMatch.length - 1]) : null;

  // numeric: dd.mm(.yyyy)? … dd.mm(.yyyy)?
  const nums = [...text.matchAll(/(\d{1,2})\.(\d{1,2})(?:\.(\d{4}))?/g)];
  if (nums.length >= 2) {
    const [d1, m1, y1] = [Number(nums[0][1]), Number(nums[0][2]), nums[0][3] ? Number(nums[0][3]) : null];
    const [d2, m2, y2] = [Number(nums[1][1]), Number(nums[1][2]), nums[1][3] ? Number(nums[1][3]) : null];
    const endYear = y2 ?? fallbackYear;
    const startYear = y1 ?? (endYear && m1 > m2 ? endYear - 1 : endYear);
    if (startYear && endYear) return { startDate: iso(startYear, m1, d1), endDate: iso(endYear, m2, d2) };
  }

  // russian words: "01 июня – 21 июня 2026" / "с 3 по 23 июля 2026" / "3-23 июля 2026"
  const words = [...text.matchAll(/(\d{1,2})\s*(?:[–—-]\s*(\d{1,2})\s+)?([А-Яа-яё]{3,8})/g)];
  if (fallbackYear && words.length) {
    const first = words[0];
    const m1 = monthFromWord(first[3]);
    if (m1 && first[2]) return { startDate: iso(fallbackYear, m1, Number(first[1])), endDate: iso(fallbackYear, m1, Number(first[2])) };
    if (m1 && words.length >= 2) {
      const m2 = monthFromWord(words[1][3]);
      if (m2) {
        const endYear = fallbackYear;
        const startYear = m1 > m2 ? endYear - 1 : endYear;
        return { startDate: iso(startYear, m1, Number(first[1])), endDate: iso(endYear, m2, Number(words[1][1])) };
      }
    }
  }
  return { startDate: null, endDate: null };
}

export function durationDays(start: string | Date, end: string | Date) {
  const a = new Date(start), b = new Date(end);
  return Math.round((b.getTime() - a.getTime()) / 86_400_000) + 1;
}

function dd(d: Date) { return `${String(d.getUTCDate()).padStart(2, "0")}.${String(d.getUTCMonth() + 1).padStart(2, "0")}`; }

/** "3 смена 13.07–02.08.2026 [21 день]" */
export function buildShiftName(index: number, start: string, end: string, label = "смена") {
  const a = new Date(start), b = new Date(end);
  const days = durationDays(a, b);
  const dayWord = days % 10 === 1 && days % 100 !== 11 ? "день" : days % 10 >= 2 && days % 10 <= 4 && (days % 100 < 10 || days % 100 >= 20) ? "дня" : "дней";
  return `${index} ${label} ${dd(a)}–${dd(b)}.${b.getUTCFullYear()} [${days} ${dayWord}]`;
}

/** "13 июля – 2 августа 2026" for public display */
export function formatShiftRange(start: Date | string | null, end: Date | string | null) {
  if (!start || !end) return null;
  const a = new Date(start), b = new Date(end);
  const sameMonth = a.getUTCMonth() === b.getUTCMonth() && a.getUTCFullYear() === b.getUTCFullYear();
  return sameMonth
    ? `${a.getUTCDate()}–${b.getUTCDate()} ${MONTH_GEN[b.getUTCMonth()]} ${b.getUTCFullYear()}`
    : `${a.getUTCDate()} ${MONTH_GEN[a.getUTCMonth()]} – ${b.getUTCDate()} ${MONTH_GEN[b.getUTCMonth()]} ${b.getUTCFullYear()}`;
}

export function addYears(date: string, years: number) {
  const d = new Date(date);
  d.setUTCFullYear(d.getUTCFullYear() + years);
  return d.toISOString().slice(0, 10);
}
