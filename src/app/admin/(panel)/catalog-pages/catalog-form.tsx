"use client";

import { AdminForm, Checkbox, F, inputCls, selectCls } from "@/components/admin/form";
import { HtmlEditor } from "@/components/admin/html-editor";
import { saveCatalogPage } from "@/server/actions/admin/content";
import { SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";

export type CatalogFormData = { id?: number; path: string; h1: string; seoTitle: string | null; metaDescription: string | null; seoText: string | null; type: string | null; regionSlugs: string[]; tagSlugs: string[]; season: string | null; isIndexable: boolean; sortOrder: number };
type Props = { page: CatalogFormData; regions: { slug: string; name: string }[]; tags: { slug: string; label: string }[] };

export function CatalogForm({ page, regions, tags }: Props) {
  return (
    <AdminForm action={saveCatalogPage}>
      {page.id ? <input type="hidden" name="id" value={page.id} /> : null}
      <div className="grid gap-6 xl:grid-cols-12">
        <div className="space-y-4 xl:col-span-7">
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <F label="Путь" name="path" hint="Часть адреса после /tour/filter/, например lager/crymea"><input name="path" defaultValue={page.path} required className={inputCls} /></F>
            <F label="H1" name="h1" hint="{year} подставит текущий год"><input name="h1" defaultValue={page.h1} required className={inputCls} /></F>
            <F label="SEO title" name="seoTitle"><input name="seoTitle" defaultValue={page.seoTitle ?? ""} className={inputCls} /></F>
            <F label="Meta description" name="metaDescription"><textarea name="metaDescription" defaultValue={page.metaDescription ?? ""} rows={3} className={inputCls} /></F>
          </section>
          <HtmlEditor name="seoText" defaultValue={page.seoText ?? ""} label="SEO-текст под списком туров" rows={10} />
        </div>
        <div className="space-y-4 xl:col-span-5">
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <h2 className="text-lg font-extrabold">Правило отбора</h2>
            <F label="Тип" name="type"><select name="type" defaultValue={page.type ?? ""} className={selectCls}><option value="">Любой</option>{Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></F>
            <F label="Сезон" name="season"><select name="season" defaultValue={page.season ?? ""} className={selectCls}><option value="">Любой</option>{Object.entries(SEASON_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></F>
            <div><span className="label">Регионы (любой из отмеченных)</span><div className="grid grid-cols-2 gap-1.5">{regions.map((r) => <Checkbox key={r.slug} name="regionSlugs[]" value={r.slug} label={r.name} defaultChecked={page.regionSlugs.includes(r.slug)} />)}</div></div>
            <div><span className="label">Характеристики (все отмеченные)</span><div className="grid max-h-64 grid-cols-2 gap-1.5 overflow-y-auto">{tags.map((t) => <Checkbox key={t.slug} name="tagSlugs[]" value={t.slug} label={t.label} defaultChecked={page.tagSlugs.includes(t.slug)} />)}</div></div>
          </section>
          <section className="space-y-3 rounded-2xl bg-white p-5 shadow-card">
            <Checkbox name="isIndexable" label="Разрешить индексацию и включить в sitemap" defaultChecked={page.isIndexable} />
            <F label="Сортировка" name="sortOrder"><input name="sortOrder" type="number" defaultValue={page.sortOrder} className={inputCls} /></F>
          </section>
        </div>
      </div>
    </AdminForm>
  );
}
