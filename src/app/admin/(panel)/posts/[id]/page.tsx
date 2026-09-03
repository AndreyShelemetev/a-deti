import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { AdminPage } from "@/components/admin/ui";
import { DangerAction } from "@/components/admin/form";
import { PostForm } from "../post-form";
import { deletePost } from "@/server/actions/admin/content";

export default async function EditPost(props: PageProps<"/admin/posts/[id]">) {
  const { id } = await props.params;
  const post = await prisma.post.findUnique({ where: { id: Number(id) } });
  if (!post) notFound();
  const remove = deletePost.bind(null, post.id);
  const base = post.kind === "NEWS" ? "/novosti/" : "/stati/";
  return (
    <AdminPage title={post.title} actions={<><Link href={`${base}${post.slug}/`} target="_blank" className="btn-outline !py-2 !text-xs">Открыть на сайте</Link><Link href={`/admin/posts/?kind=${post.kind}`} className="btn-ghost !py-2 !text-xs">← К списку</Link></>}>
      <PostForm post={{ ...post, publishedAt: post.publishedAt.toISOString().slice(0, 10) }} />
      <div className="border-t border-ink-200 pt-4"><DangerAction action={remove} /></div>
    </AdminPage>
  );
}
