import { prisma } from "@/lib/prisma";
import { TourForm, type TourFormData } from "../tour-form";

export default async function NewTour(props: PageProps<"/admin/tours/new">) {
  const sp = await props.searchParams;
  const typeParam = Array.isArray(sp.type) ? sp.type[0] : sp.type;
  const type = typeParam && ["CAMP", "FAMILY", "SPORT", "EXCURSION", "TRANSFER", "YOUTH"].includes(typeParam) ? typeParam : "CAMP";
  const [regions, tags] = await Promise.all([prisma.region.findMany({ orderBy: { sortOrder: "asc" } }), prisma.tag.findMany({ orderBy: { sortOrder: "asc" } })]);
  const empty: TourFormData = {
    title: "", slug: "", type, regionId: null, short: "", address: "", ageText: "", ageMin: null, ageMax: null, website: null, priceText: null, priceMin: null, cover: null,
    seasons: ["SUMMER"], seasonText: null, isBest: false, isHit: false, status: "draft", internalNote: null, sortOrder: 100, seoTitle: null, metaDescription: null, metaKeywords: null,
    tagIds: [], shifts: [], images: [], sections: {}, documents: [],
  };
  return (
    <div className="space-y-4">
      <p className="text-sm text-ink-500">Заполните шаги по порядку. Тур создаётся как черновик и не виден на сайте, пока вы не выберете статус «Опубликован» на последнем шаге.</p>
      <TourForm tour={empty} regions={regions} tags={tags} />
    </div>
  );
}
