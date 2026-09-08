/** Backfills TourShift.startDate/endDate from the shift name where the dates were not recognised. Run: pnpm exec tsx scripts/fix-shift-dates.ts */
import "dotenv/config";
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient } from "../src/generated/prisma/client";
import { parseShiftDates } from "../src/lib/shifts";

const prisma = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });
async function main() {
const shifts = await prisma.tourShift.findMany({ where: { startDate: null }, include: { tour: { select: { title: true, seasonText: true } } } });
let fixed = 0;
for (const s of shifts) {
  // no year in the name → take it from the tour ("лето 2027", "… Лето 2026 год")
  const hasYear = /\b20\d{2}\b/.test(s.name);
  const tourYear = `${s.tour.seasonText ?? ""} ${s.tour.title}`.match(/\b20\d{2}\b/)?.[0];
  const { startDate, endDate } = parseShiftDates(hasYear || !tourYear ? s.name : `${s.name} ${tourYear}`);
  if (startDate && endDate) {
    await prisma.tourShift.update({ where: { id: s.id }, data: { startDate: new Date(startDate), endDate: new Date(endDate) } });
    fixed++;
  } else console.log("still unparsed:", s.tourId, s.name);
}
console.log(`fixed ${fixed} of ${shifts.length}`);
}
main().finally(() => prisma.$disconnect());
