import type { Metadata } from "next";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";
import { listTours } from "@/server/queries/tours";
import { TourCard } from "@/components/tour/tour-card";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("transfer-do-lagerya");
}

export default async function TransferToCampPage() {
  const transfers = await listTours({ type: "TRANSFER", perPage: 8 });
  return (
    <StaticPage slug="transfer-do-lagerya">
      {transfers.items.length ? (
        <section className="mt-8">
          <h2 className="section-title mb-5 !text-2xl">Трансферы в лагеря</h2>
          <div className="grid gap-5 sm:grid-cols-2">{transfers.items.map((t) => <TourCard key={t.id} tour={t} />)}</div>
        </section>
      ) : null}
    </StaticPage>
  );
}
