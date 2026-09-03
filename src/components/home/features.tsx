import { SafeImage } from "@/components/ui/safe-image";
import { SectionTitle } from "@/components/ui/section-title";
import type { Feature } from "@/generated/prisma/client";

const EXTRA = [
  { emoji: "🧾", text: "Официальный договор и полный пакет документов для лагеря" },
  { emoji: "🚆", text: "Сопровождение детей в поезде и самолёте, встреча на месте" },
  { emoji: "💬", text: "Менеджер на связи в течение всей смены" },
];

export function Features({ features }: { features: Feature[] }) {
  return (
    <section className="container-site py-12">
      <SectionTitle accent="Коротко" subtitle="Почему родители выбирают А-тур уже больше 20 лет.">о нас</SectionTitle>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {features.map((f) => (
          <div key={f.id} className="card flex items-center gap-4 p-5">
            <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-brand-50">
              {f.icon ? <SafeImage src={f.icon} alt="" fill sizes="64px" className="object-contain p-1" /> : null}
            </div>
            <p className="font-bold text-ink-800">{f.text}</p>
          </div>
        ))}
        {EXTRA.map((f) => (
          <div key={f.text} className="card flex items-center gap-4 p-5">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-sun-100 text-3xl">{f.emoji}</div>
            <p className="font-bold text-ink-800">{f.text}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
