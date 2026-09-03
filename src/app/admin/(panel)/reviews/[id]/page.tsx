import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage, Panel } from "@/components/admin/ui";
import { DangerAction } from "@/components/admin/form";
import { ReviewForm } from "../review-form";
import { deleteReview } from "@/server/actions/admin/misc";

export default async function EditReview(props: PageProps<"/admin/reviews/[id]">) {
  const { id } = await props.params;
  const tours = await prisma.tour.findMany({ select: { id: true, title: true }, orderBy: { title: "asc" } });
  if (id === "new") {
    return (
      <AdminPage title="Новый отзыв"><Panel><ReviewForm review={{ author: "", text: "", rating: 5, source: "SITE", sourceUrl: null, publishedAt: new Date().toISOString().slice(0, 10), isPublished: true, tourId: null }} tours={tours} /></Panel></AdminPage>
    );
  }
  const review = await prisma.review.findUnique({ where: { id: Number(id) } });
  if (!review) notFound();
  const remove = deleteReview.bind(null, review.id);
  return (
    <AdminPage title={`Отзыв: ${review.author}`}>
      <Panel><ReviewForm review={{ ...review, publishedAt: review.publishedAt.toISOString().slice(0, 10) }} tours={tours} /></Panel>
      <DangerAction action={remove} />
    </AdminPage>
  );
}
