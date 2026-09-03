import Link from "next/link";
import { Flame } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";
import { SectionTitle } from "@/components/ui/section-title";
import type { Slide } from "@/generated/prisma/client";

export function HitCards({ hits }: { hits: Slide[] }) {
  if (!hits.length) return null;
  return (
    <section className="container-site py-12">
      <SectionTitle accent="Хиты" subtitle="Программы, которые родители бронируют чаще всего.">продаж</SectionTitle>
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {hits.map((h) => (
          <Link key={h.id} href={h.href ?? "/tour/"} className="group relative block aspect-[3/2] overflow-hidden rounded-3xl shadow-card">
            <SafeImage src={h.image} alt={h.title ?? ""} fill sizes="(max-width: 640px) 100vw, 33vw" className="object-cover transition-transform duration-700 group-hover:scale-105" />
            <div className="absolute inset-0 bg-gradient-to-t from-ink-900/75 via-ink-900/10 to-transparent" />
            <span className="absolute left-4 top-4 inline-flex items-center gap-1 rounded-full bg-coral-500 px-3 py-1 text-xs font-extrabold text-white"><Flame className="h-3.5 w-3.5" /> Хит</span>
            <div className="absolute bottom-4 left-4 right-4 text-lg font-extrabold leading-snug text-white">{h.title}</div>
          </Link>
        ))}
      </div>
    </section>
  );
}
