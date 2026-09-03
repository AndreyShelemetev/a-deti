"use client";

import { useState } from "react";
import { AdminForm, Checkbox, DangerAction, F, inputCls } from "@/components/admin/form";
import { deleteRegion, deleteTag, saveRegion, saveTag } from "@/server/actions/admin/content";

type Item = { id?: number; slug: string; label: string; h1?: string | null; seoTitle?: string | null; metaDescription?: string | null; isForeign?: boolean; sortOrder: number; count?: number };

export function TaxonomyEditor({ kind, items }: { kind: "tag" | "region"; items: Item[] }) {
  const [editing, setEditing] = useState<Item | null>(null);
  const action = kind === "tag" ? saveTag : saveRegion;
  const remove = kind === "tag" ? deleteTag : deleteRegion;
  return (
    <div>
      <ul className="divide-y divide-ink-100 text-sm">
        {items.map((it) => (
          <li key={it.id} className="flex items-center gap-3 py-2">
            <button type="button" onClick={() => setEditing(it)} className="flex-1 text-left font-bold text-brand-600 hover:underline">{it.label}</button>
            <span className="text-xs text-ink-400">{it.slug} · {it.count ?? 0}</span>
            {it.id ? <DangerAction action={remove.bind(null, it.id)} label="" confirm="Удалить? Связи с турами будут сняты." /> : null}
          </li>
        ))}
      </ul>
      <button type="button" onClick={() => setEditing({ slug: "", label: "", sortOrder: items.length })} className="btn-outline mt-3 !py-2 !text-xs">+ Добавить</button>
      {editing ? (
        <div className="mt-4 rounded-2xl bg-ink-50 p-4">
          <AdminForm key={editing.id ?? "new"} action={action}>
            {editing.id ? <input type="hidden" name="id" value={editing.id} /> : null}
            <div className="grid gap-3 sm:grid-cols-2">
              <F label="Название" name={kind === "tag" ? "label" : "name"}><input name={kind === "tag" ? "label" : "name"} defaultValue={editing.label} required className={inputCls} /></F>
              <F label="Slug" name="slug"><input name="slug" defaultValue={editing.slug} className={inputCls} /></F>
              <F label="Сортировка" name="sortOrder"><input name="sortOrder" type="number" defaultValue={editing.sortOrder} className={inputCls} /></F>
              {kind === "region" ? <div className="flex items-end pb-2"><Checkbox name="isForeign" label="За границей" defaultChecked={editing.isForeign} /></div> : null}
              {kind === "tag" ? (
                <>
                  <F label="H1 страницы фильтра" name="h1" className="sm:col-span-2"><input name="h1" defaultValue={editing.h1 ?? ""} className={inputCls} /></F>
                  <F label="SEO title" name="seoTitle" className="sm:col-span-2"><input name="seoTitle" defaultValue={editing.seoTitle ?? ""} className={inputCls} /></F>
                  <F label="Meta description" name="metaDescription" className="sm:col-span-2"><textarea name="metaDescription" defaultValue={editing.metaDescription ?? ""} rows={2} className={inputCls} /></F>
                </>
              ) : null}
            </div>
          </AdminForm>
          <button type="button" onClick={() => setEditing(null)} className="mt-2 text-xs font-bold text-ink-500 hover:underline">Закрыть</button>
        </div>
      ) : null}
    </div>
  );
}
