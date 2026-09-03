import { AdminPage } from "@/components/admin/ui";
import { PostForm } from "../post-form";

export default async function NewPost(props: PageProps<"/admin/posts/new">) {
  const sp = await props.searchParams;
  const kind = (Array.isArray(sp.kind) ? sp.kind[0] : sp.kind) === "ARTICLE" ? "ARTICLE" : "NEWS";
  return (
    <AdminPage title={kind === "NEWS" ? "Новая новость" : "Новая статья"}>
      <PostForm post={{ kind, title: "", slug: "", excerpt: null, image: null, html: "", publishedAt: new Date().toISOString().slice(0, 10), isPublished: true, seoTitle: null, metaDescription: null }} />
    </AdminPage>
  );
}
