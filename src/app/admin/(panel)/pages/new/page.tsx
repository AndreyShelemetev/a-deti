import { AdminPage } from "@/components/admin/ui";
import { PageForm } from "../page-form";

export default function NewPage() {
  return (
    <AdminPage title="Новая страница" description="Новая страница откроется по адресу /slug/ (обрабатывается динамически, без правки кода).">
      <PageForm page={{ slug: "", title: "", html: "", isPublished: true, seoTitle: null, metaDescription: null, metaKeywords: null }} />
    </AdminPage>
  );
}
