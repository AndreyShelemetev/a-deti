import Link from "next/link";
import { MapPin, Star } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";
import { Badge, SEASON_TONE } from "@/components/ui/badge";
import { SEASON_LABELS } from "@/config/site";
import { cn, formatPrice } from "@/lib/utils";
import type { TourCard as TourCardData } from "@/server/queries/tours";

type Props = { tour: TourCardData; className?: string; priorityImage?: boolean };

export function priceLabel(tour: { priceMin: number | null; priceText: string | null }) {
  if (tour.priceMin) return `от ${formatPrice(tour.priceMin)}`;
  if (tour.priceText && !/запрос/i.test(tour.priceText)) return tour.priceText;
  return "Цена по запросу";
}

export function TourCard({ tour, className, priorityImage }: Props) {
  const href = `/tour/${tour.slug}/`;
  const seasons = tour.seasons.slice(0, 2);
  return (
    <article className={cn("card group relative flex h-full flex-col overflow-hidden transition-transform duration-200 hover:-translate-y-1 hover:shadow-soft", className)}>
      <Link href={href} className="relative block aspect-[4/3] overflow-hidden bg-brand-50" tabIndex={-1} aria-hidden>
        <SafeImage src={tour.cover} alt="" fill sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw" className="object-cover transition-transform duration-500 group-hover:scale-105" preload={priorityImage} />
        <div className="absolute left-3 top-3 flex flex-wrap gap-1.5">
          {tour.isBest ? <Badge tone="sun"><Star className="h-3 w-3 fill-current" /> Выбор родителей</Badge> : null}
          {seasons.map((s) => <Badge key={s} tone={SEASON_TONE[s] ?? "brand"}>{SEASON_LABELS[s]}</Badge>)}
        </div>
      </Link>
      <div className="flex flex-1 flex-col p-4 sm:p-5">
        <h3 className="text-lg font-extrabold leading-snug text-ink-900">
          <Link href={href} className="after:absolute after:inset-0 after:content-[''] hover:text-brand-700">{tour.title}</Link>
        </h3>
        {tour.address ? (
          <p className="mt-1.5 flex items-start gap-1 text-xs font-semibold text-ink-500"><MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-coral-500" /><span className="line-clamp-1">{tour.address}</span></p>
        ) : null}
        {tour.short ? <p className="mt-2 line-clamp-3 text-sm text-ink-600">{tour.short}</p> : null}
        <div className="mt-3 flex flex-wrap gap-1.5">
          {tour.ageText ? <Badge tone="berry">{tour.ageText}</Badge> : null}
          {tour.tags.slice(0, 3).map((t) => <Badge key={t.tag.slug} tone="ink">{t.tag.label}</Badge>)}
        </div>
        <div className="mt-auto flex items-end justify-between gap-3 pt-4">
          <div>
            <div className="text-[11px] font-bold uppercase tracking-wide text-ink-400">Стоимость</div>
            <div className={cn("text-lg font-extrabold", tour.priceMin ? "text-brand-700" : "text-ink-600")}>{priceLabel(tour)}</div>
          </div>
          <span className="btn-primary relative z-10 !py-2 !text-xs">Подробнее</span>
        </div>
      </div>
    </article>
  );
}
