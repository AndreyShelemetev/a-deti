import Link from "next/link";
import { ArrowRight, ShieldCheck, Sparkles, Sun } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";
import { QuickSearch } from "./quick-search";
import { plural } from "@/lib/utils";
import type { Slide } from "@/generated/prisma/client";

type Props = { slides: Slide[]; facets: { regions: { slug: string; name: string }[] }; toursCount: number };

export function Hero({ slides, facets, toursCount }: Props) {
  const main = slides[0];
  const rest = slides.slice(1, 4);
  return (
    <section className="relative overflow-hidden bg-dots">
      <div className="blob -left-24 top-10 h-72 w-72 bg-sun-200/70" />
      <div className="blob -right-16 top-40 h-80 w-80 bg-berry-100/80" />
      <div className="blob bottom-0 left-1/3 h-64 w-64 bg-mint-100" />
      <div className="container-site relative grid items-center gap-10 py-10 lg:grid-cols-12 lg:py-16">
        <div className="lg:col-span-6">
          <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 text-xs font-extrabold text-brand-700 shadow-card">
            <ShieldCheck className="h-4 w-4 text-mint-500" /> Туроператор в федеральном реестре с 2005 года
          </div>
          <h1 className="text-4xl font-black leading-[1.05] text-ink-900 sm:text-5xl lg:text-[3.6rem]">
            Лучшее лето <span className="relative inline-block text-brand-500">вашего ребёнка<svg className="absolute -bottom-1 left-0 h-3 w-full text-sun-400" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden><path d="M2 9 Q50 2 100 8 T198 6" stroke="currentColor" strokeWidth="5" fill="none" strokeLinecap="round" /></svg></span> начинается здесь
          </h1>
          <p className="mt-5 max-w-xl text-lg text-ink-600">
            Детские лагеря на море, в Подмосковье, на Алтае и за рубежом, спортивные сборы и экскурсии. Подбираем программу под возраст и интересы, помогаем с документами и доставкой.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link href="/tour/" className="btn-primary">Смотреть {toursCount} {plural(toursCount, ["тур", "тура", "туров"])} <ArrowRight className="h-4 w-4" /></Link>
            <Link href="/tour/filter/lager/sea-camps/" className="btn-sun"><Sun className="h-4 w-4" /> Лагеря на море</Link>
            <Link href="/tour/filter/lager/foreign-camps/" className="btn-outline"><Sparkles className="h-4 w-4" /> За границей</Link>
          </div>
          <dl className="mt-8 grid max-w-lg grid-cols-3 gap-4">
            {[["20+", "лет с детьми"], ["10 000+", "счастливых детей"], ["4,5 / 5", "рейтинг родителей"]].map(([v, l]) => (
              <div key={l}><dt className="text-2xl font-black text-ink-900 sm:text-3xl">{v}</dt><dd className="text-xs font-semibold text-ink-500 sm:text-sm">{l}</dd></div>
            ))}
          </dl>
        </div>
        <div className="lg:col-span-6">
          <div className="grid grid-cols-3 gap-3 sm:gap-4">
            {main ? (
              <Link href={main.href ?? "/tour/"} className="group relative col-span-2 row-span-2 block overflow-hidden rounded-4xl shadow-soft">
                <div className="aspect-[4/3] h-full w-full" />
                <SafeImage src={main.image} alt={main.title ?? "Детский отдых"} fill sizes="(max-width: 1024px) 66vw, 40vw" className="object-cover transition-transform duration-700 group-hover:scale-105" preload />
                <div className="absolute inset-0 bg-gradient-to-t from-ink-900/60 via-transparent" />
                <div className="absolute bottom-4 left-4 rounded-2xl bg-sun-300 px-4 py-2 text-sm font-black text-ink-900 shadow-card">Родители доверяют нам с 2005 года ☀️</div>
              </Link>
            ) : null}
            {rest.slice(0, 2).map((s, i) => (
              <Link key={s.id} href={s.href ?? "/tour/"} className={`group relative block aspect-[4/3] overflow-hidden rounded-3xl shadow-card ${i === 0 ? "animate-float" : ""}`}>
                <SafeImage src={s.image} alt={s.title ?? ""} fill sizes="(max-width: 1024px) 33vw, 20vw" className="object-cover transition-transform duration-700 group-hover:scale-105" />
              </Link>
            ))}
          </div>
        </div>
      </div>
      <div className="container-site relative pb-10">
        <QuickSearch regions={facets.regions} />
      </div>
    </section>
  );
}
