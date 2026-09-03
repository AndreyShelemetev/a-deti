"use client";

import { AdminForm, Checkbox, F, inputCls } from "@/components/admin/form";
import { HtmlEditor } from "@/components/admin/html-editor";
import { ImageField } from "@/components/admin/image-field";
import { savePost } from "@/server/actions/admin/content";

export type PostFormData = { id?: number; kind: "NEWS" | "ARTICLE"; title: string; slug: string; excerpt: string | null; image: string | null; html: string; publishedAt: string; isPublished: boolean; seoTitle: string | null; metaDescription: string | null };

export function PostForm({ post }: { post: PostFormData }) {
  return (
    <AdminForm action={savePost} submitLabel={post.id ? "Сохранить" : "Создать"}>
      {post.id ? <input type="hidden" name="id" value={post.id} /> : null}
      <input type="hidden" name="kind" value={post.kind} />
      <div className="grid gap-6 xl:grid-cols-12">
        <div className="space-y-4 xl:col-span-8">
          <F label="Заголовок" name="title"><input name="title" defaultValue={post.title} required className={inputCls} /></F>
          <F label="Анонс (для списка)" name="excerpt" hint="Пусто — сформируется из текста"><textarea name="excerpt" defaultValue={post.excerpt ?? ""} rows={2} className={inputCls} /></F>
          <HtmlEditor name="html" defaultValue={post.html} label="Текст" />
        </div>
        <div className="space-y-4 xl:col-span-4">
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <Checkbox name="isPublished" label="Опубликовано" defaultChecked={post.isPublished} />
            <F label="Дата публикации" name="publishedAt"><input name="publishedAt" type="date" defaultValue={post.publishedAt} className={inputCls} /></F>
            <F label="URL (slug)" name="slug"><input name="slug" defaultValue={post.slug} className={inputCls} /></F>
          </section>
          <section className="rounded-2xl bg-white p-5 shadow-card"><ImageField name="image" defaultValue={post.image} label="Обложка" /></section>
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <F label="SEO title" name="seoTitle"><input name="seoTitle" defaultValue={post.seoTitle ?? ""} className={inputCls} /></F>
            <F label="Meta description" name="metaDescription"><textarea name="metaDescription" defaultValue={post.metaDescription ?? ""} rows={3} className={inputCls} /></F>
          </section>
        </div>
      </div>
    </AdminForm>
  );
}
