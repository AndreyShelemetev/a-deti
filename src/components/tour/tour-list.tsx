import { TourCard } from "./tour-card";
import type { TourCard as TourCardData } from "@/server/queries/tours";

export function TourList({ items, emptyText = "По вашему запросу туров пока нет. Попробуйте изменить фильтры или напишите нам — подберём вариант вручную." }: { items: TourCardData[]; emptyText?: string }) {
  if (!items.length) {
    return (
      <div className="card p-10 text-center">
        <div className="mx-auto mb-3 h-16 w-16 rounded-full bg-sun-100 text-4xl leading-[4rem]">🏕️</div>
        <p className="mx-auto max-w-md text-ink-600">{emptyText}</p>
      </div>
    );
  }
  return (
    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      {items.map((t, i) => <TourCard key={t.id} tour={t} priorityImage={i < 3} />)}
    </div>
  );
}
