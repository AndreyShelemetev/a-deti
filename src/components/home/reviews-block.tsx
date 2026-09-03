import { ExternalLink, Star } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";
import { SectionTitle } from "@/components/ui/section-title";
import { ReviewForm } from "@/components/forms/review-form";
import { formatDateShort } from "@/lib/utils";
import type { Review, ReviewPlatform } from "@/generated/prisma/client";

type Props = { platforms: ReviewPlatform[]; reviews: Review[]; intro?: string; tourId?: number; compact?: boolean };

function Stars({ n }: { n: number }) {
  return (
    <span className="inline-flex" aria-label={`${n} из 5`}>
      {[1, 2, 3, 4, 5].map((i) => <Star key={i} className={`h-4 w-4 ${i <= n ? "fill-sun-400 text-sun-400" : "text-ink-200"}`} />)}
    </span>
  );
}

export function ReviewsBlock({ platforms, reviews, intro, tourId, compact = false }: Props) {
  return (
    <section className={compact ? "" : "container-site py-12"}>
      {!compact ? <SectionTitle accent="Отзывы" subtitle={intro ?? "Мы публикуем только живые отзывы родителей."}>о нас</SectionTitle> : null}
      <div className="grid gap-6 lg:grid-cols-12">
        <div className="space-y-4 lg:col-span-4">
          <div className="card p-5">
            <div className="mb-3 text-sm font-extrabold uppercase tracking-wide text-ink-500">Наш рейтинг</div>
            <div className="space-y-3">
              {platforms.map((p) => (
                <a key={p.id} href={p.url} target="_blank" rel="noopener" className="flex items-center gap-3 rounded-2xl bg-ink-50 p-3 hover:bg-brand-50">
                  <div className="relative h-9 w-9 shrink-0 overflow-hidden rounded-lg bg-white">{p.logo ? <SafeImage src={p.logo} alt={p.name} fill sizes="36px" className="object-contain p-1" /> : null}</div>
                  <div className="flex-1"><div className="text-sm font-extrabold text-ink-900">{p.name}</div><Stars n={Math.round(p.rating)} /></div>
                  <div className="text-xl font-black text-ink-900">{p.rating.toString().replace(".", ",")}<span className="text-sm text-ink-400"> / {p.max}</span></div>
                </a>
              ))}
            </div>
          </div>
          <details className="card group p-5">
            <summary className="cursor-pointer list-none text-base font-extrabold text-brand-700 marker:hidden">+ Оставить свой отзыв</summary>
            <div className="mt-4"><ReviewForm tourId={tourId} /></div>
          </details>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:col-span-8">
          {reviews.map((r) => (
            <article key={r.id} className="card flex flex-col p-5">
              <div className="flex items-center justify-between gap-2">
                <div className="font-extrabold text-ink-900">{r.author}</div>
                <Stars n={r.rating} />
              </div>
              <p className="mt-2 flex-1 text-sm text-ink-700">{r.text}</p>
              <div className="mt-3 flex items-center justify-between text-xs text-ink-400">
                <span>{formatDateShort(r.publishedAt)}</span>
                {r.sourceUrl ? <a href={r.sourceUrl} target="_blank" rel="noopener" className="inline-flex items-center gap-1 font-bold text-brand-600 hover:underline">{r.source === "YANDEX" ? "Яндекс" : r.source === "GOOGLE" ? "Google" : "Сайт"} <ExternalLink className="h-3 w-3" /></a> : <span>{r.source === "SITE" ? "С сайта" : r.source}</span>}
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
