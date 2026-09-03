"use client";

import { AdminForm, Checkbox, F, inputCls } from "@/components/admin/form";
import { HtmlEditor } from "@/components/admin/html-editor";
import { savePage } from "@/server/actions/admin/content";

export type PageFormData = { id?: number; slug: string; title: string; html: string; isPublished: boolean; seoTitle: string | null; metaDescription: string | null; metaKeywords: string | null };

export function PageForm({ page }: { page: PageFormData }) {
  return (
    <AdminForm action={savePage}>
      {page.id ? <input type="hidden" name="id" value={page.id} /> : null}
      <div className="grid gap-6 xl:grid-cols-12">
        <div className="space-y-4 xl:col-span-8">
          <F label="Заголовок" name="title"><input name="title" defaultValue={page.title} required className={inputCls} /></F>
          <HtmlEditor name="html" defaultValue={page.html} label="Содержимое" />
        </div>
        <div className="space-y-4 xl:col-span-4">
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <Checkbox name="isPublished" label="Опубликована" defaultChecked={page.isPublished} />
            <F label="Slug (адрес)" name="slug" hint="Страница откроется по адресу /slug/. Для служебных страниц (pay, kak-nas-nayti, agentstvam) slug менять нельзя."><input name="slug" defaultValue={page.slug} required className={inputCls} /></F>
          </section>
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <F label="SEO title" name="seoTitle"><input name="seoTitle" defaultValue={page.seoTitle ?? ""} className={inputCls} /></F>
            <F label="Meta description" name="metaDescription"><textarea name="metaDescription" defaultValue={page.metaDescription ?? ""} rows={3} className={inputCls} /></F>
            <F label="Keywords" name="metaKeywords"><input name="metaKeywords" defaultValue={page.metaKeywords ?? ""} className={inputCls} /></F>
          </section>
        </div>
      </div>
    </AdminForm>
  );
}
