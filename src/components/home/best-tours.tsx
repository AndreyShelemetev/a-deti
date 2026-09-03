import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { SectionTitle } from "@/components/ui/section-title";
import { TourCard } from "@/components/tour/tour-card";
import type { TourCard as TourCardData } from "@/server/queries/tours";

type Props = { accent: string; title: string; tours: TourCardData[]; links: { href: string; label: string }[]; allHref: string; tone?: "brand" | "sand" };

export function BestTours({ accent, title, tours, links, allHref, tone = "brand" }: Props) {
  if (!tours.length) return null;
  return (
    <section className={tone === "sand" ? "bg-sand py-12" : "py-12"}>
      <div className="container-site">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <SectionTitle accent={accent} className="mb-0">{title}</SectionTitle>
          <Link href={allHref} className="btn-ghost">Все предложения <ArrowRight className="h-4 w-4" /></Link>
        </div>
        <div className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {tours.map((t) => <TourCard key={t.id} tour={t} />)}
        </div>
        {links.length ? (
          <div className="mt-6 flex flex-wrap gap-2">
            {links.map((l) => <Link key={l.href} href={l.href} className="chip bg-white text-ink-700 shadow-card hover:bg-brand-50 hover:text-brand-700">{l.label}</Link>)}
          </div>
        ) : null}
      </div>
    </section>
  );
}
