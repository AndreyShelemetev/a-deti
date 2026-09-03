import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { TourForm, type TourFormData } from "../tour-form";

export default async function NewTour() {
  const [regions, tags] = await Promise.all([prisma.region.findMany({ orderBy: { sortOrder: "asc" } }), prisma.tag.findMany({ orderBy: { sortOrder: "asc" } })]);
  const empty: TourFormData = {
    title: "", slug: "", type: "CAMP", regionId: null, short: "", address: "", ageText: "", ageMin: null, ageMax: null, website: null, priceText: null, priceMin: null, cover: null,
    seasons: ["SUMMER"], seasonText: null, isBest: false, isHit: false, isPublished: false, sortOrder: 100, seoTitle: null, metaDescription: null, metaKeywords: null,
    tagIds: [], shifts: [], images: [], sections: {}, documents: [],
  };
  return (
    <AdminPage title="Новый тур">
      <TourForm tour={empty} regions={regions} tags={tags} />
    </AdminPage>
  );
}
