import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { DangerAction } from "@/components/admin/form";
import { TourForm, type TourFormData } from "../tour-form";
import { deleteTour } from "@/server/actions/admin/tours";

export default async function EditTour(props: PageProps<"/admin/tours/[id]">) {
  const { id } = await props.params;
  const [tour, regions, tags] = await Promise.all([
    prisma.tour.findUnique({ where: { id: Number(id) }, include: { shifts: { orderBy: { sortOrder: "asc" } }, images: { orderBy: { sortOrder: "asc" } }, sections: true, tags: true, documents: { orderBy: { sortOrder: "asc" } } } }),
    prisma.region.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.tag.findMany({ orderBy: { sortOrder: "asc" } }),
  ]);
  if (!tour) notFound();
  const data: TourFormData = {
    id: tour.id, title: tour.title, slug: tour.slug, type: tour.type, regionId: tour.regionId, short: tour.short, address: tour.address, ageText: tour.ageText, ageMin: tour.ageMin, ageMax: tour.ageMax,
    website: tour.website, priceText: tour.priceText, priceMin: tour.priceMin, cover: tour.cover, seasons: tour.seasons, seasonText: tour.seasonText, isBest: tour.isBest, isHit: tour.isHit, isPublished: tour.isPublished, sortOrder: tour.sortOrder,
    seoTitle: tour.seoTitle, metaDescription: tour.metaDescription, metaKeywords: tour.metaKeywords,
    tagIds: tour.tags.map((t) => t.tagId),
    shifts: tour.shifts.map((s) => ({ group: s.group, name: s.name, price: s.price, startDate: s.startDate?.toISOString().slice(0, 10) ?? null, endDate: s.endDate?.toISOString().slice(0, 10) ?? null })),
    images: tour.images.map((i) => ({ url: i.url, alt: i.alt })),
    sections: Object.fromEntries(tour.sections.map((s) => [s.key, { title: s.title, html: s.html }])),
    documents: tour.documents.map((d) => ({ label: d.label, url: d.url })),
  };
  const remove = deleteTour.bind(null, tour.id);
  return (
    <AdminPage title={tour.title} actions={<><Link href={`/tour/${tour.slug}/`} target="_blank" className="btn-outline !py-2 !text-xs">Открыть на сайте</Link><Link href="/admin/tours/" className="btn-ghost !py-2 !text-xs">← Все туры</Link></>}>
      <TourForm tour={data} regions={regions} tags={tags} />
      <div className="border-t border-ink-200 pt-4"><DangerAction action={remove} label="Удалить тур" confirm="Удалить тур вместе со сменами, фото и вкладками?" /></div>
    </AdminPage>
  );
}
