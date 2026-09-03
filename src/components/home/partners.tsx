import { SafeImage } from "@/components/ui/safe-image";
import { SectionTitle } from "@/components/ui/section-title";
import type { Partner } from "@/generated/prisma/client";

export function Partners({ partners }: { partners: Partner[] }) {
  if (!partners.length) return null;
  return (
    <section className="container-site py-12">
      <SectionTitle accent="Наши">партнёры</SectionTitle>
      <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {partners.map((p) => (
          <li key={p.id} className="card flex h-28 items-center justify-center p-4 grayscale transition hover:grayscale-0" title={p.name}>
            <div className="relative h-full w-full">
              <SafeImage src={p.logo} alt={p.name} fill sizes="200px" className="object-contain" />
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
