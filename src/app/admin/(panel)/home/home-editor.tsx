"use client";

import { useState } from "react";
import Image from "next/image";
import { AdminForm, Checkbox, DangerAction, F, inputCls, selectCls } from "@/components/admin/form";
import { ImageField } from "@/components/admin/image-field";
import { deleteFeature, deletePartner, deletePlatform, deleteSlide, saveFeature, savePartner, savePlatform, saveSlide } from "@/server/actions/admin/misc";

type Kind = "slide" | "feature" | "partner" | "platform";
type AnyItem = Record<string, unknown> & { id: number };

const CONFIG = {
  slide: { save: saveSlide, remove: deleteSlide, label: (i: AnyItem) => `${i.placement === "HERO" ? "Слайдер" : "Хит"} · ${(i.title as string) || (i.href as string) || "без названия"}`, image: "image" },
  feature: { save: saveFeature, remove: deleteFeature, label: (i: AnyItem) => i.text as string, image: "icon" },
  partner: { save: savePartner, remove: deletePartner, label: (i: AnyItem) => i.name as string, image: "logo" },
  platform: { save: savePlatform, remove: deletePlatform, label: (i: AnyItem) => `${i.name} · ${i.rating}`, image: "logo" },
} as const;

export function HomeBlocksEditor({ kind, items }: { kind: Kind; items: AnyItem[] }) {
  const [editing, setEditing] = useState<AnyItem | null>(null);
  const cfg = CONFIG[kind];
  return (
    <div>
      <ul className="divide-y divide-ink-100 text-sm">
        {items.map((it) => (
          <li key={it.id} className="flex items-center gap-3 py-2">
            <div className="relative h-10 w-16 shrink-0 overflow-hidden rounded-lg bg-ink-100">{it[cfg.image] ? <Image src={it[cfg.image] as string} alt="" fill sizes="64px" className="object-contain" /> : null}</div>
            <button type="button" onClick={() => setEditing(it)} className="flex-1 text-left font-bold text-brand-600 hover:underline">{cfg.label(it)}</button>
            {"isActive" in it && it.isActive === false ? <span className="chip bg-ink-100 text-ink-500">скрыт</span> : null}
            <DangerAction action={cfg.remove.bind(null, it.id)} label="" />
          </li>
        ))}
      </ul>
      <button type="button" onClick={() => setEditing({ id: 0 })} className="btn-outline mt-3 !py-2 !text-xs">+ Добавить</button>
      {editing ? (
        <div className="mt-4 rounded-2xl bg-ink-50 p-4">
          <AdminForm key={editing.id} action={cfg.save}>
            {editing.id ? <input type="hidden" name="id" value={editing.id} /> : null}
            <div className="grid gap-3 sm:grid-cols-2">
              {kind === "slide" ? (
                <>
                  <F label="Размещение" name="placement"><select name="placement" defaultValue={(editing.placement as string) ?? "HERO"} className={selectCls}><option value="HERO">Слайдер (HERO)</option><option value="HIT">Хиты продаж</option></select></F>
                  <F label="Ссылка" name="href"><input name="href" defaultValue={(editing.href as string) ?? ""} className={inputCls} placeholder="/tour/…/" /></F>
                  <F label="Заголовок" name="title" className="sm:col-span-2"><input name="title" defaultValue={(editing.title as string) ?? ""} className={inputCls} /></F>
                  <div className="sm:col-span-2"><ImageField name="image" defaultValue={editing.image as string} /></div>
                  <div><Checkbox name="isActive" label="Показывать" defaultChecked={editing.id ? Boolean(editing.isActive) : true} /></div>
                </>
              ) : null}
              {kind === "feature" ? (
                <>
                  <F label="Текст" name="text" className="sm:col-span-2"><input name="text" defaultValue={(editing.text as string) ?? ""} className={inputCls} /></F>
                  <div className="sm:col-span-2"><ImageField name="icon" defaultValue={editing.icon as string} label="Иконка" /></div>
                </>
              ) : null}
              {kind === "partner" ? (
                <>
                  <F label="Название" name="name"><input name="name" defaultValue={(editing.name as string) ?? ""} className={inputCls} /></F>
                  <F label="Сайт" name="url"><input name="url" defaultValue={(editing.url as string) ?? ""} className={inputCls} /></F>
                  <div className="sm:col-span-2"><ImageField name="logo" defaultValue={editing.logo as string} label="Логотип" /></div>
                  <div><Checkbox name="isActive" label="Показывать" defaultChecked={editing.id ? Boolean(editing.isActive) : true} /></div>
                </>
              ) : null}
              {kind === "platform" ? (
                <>
                  <F label="Площадка" name="name"><input name="name" defaultValue={(editing.name as string) ?? ""} className={inputCls} /></F>
                  <F label="Рейтинг (0–5)" name="rating"><input name="rating" type="number" step="0.1" min="0" max="5" defaultValue={(editing.rating as number) ?? 5} className={inputCls} /></F>
                  <F label="Ссылка на отзывы" name="url" className="sm:col-span-2"><input name="url" defaultValue={(editing.url as string) ?? ""} className={inputCls} /></F>
                  <div className="sm:col-span-2"><ImageField name="logo" defaultValue={editing.logo as string} label="Логотип" /></div>
                </>
              ) : null}
              <F label="Сортировка" name="sortOrder"><input name="sortOrder" type="number" defaultValue={(editing.sortOrder as number) ?? 0} className={inputCls} /></F>
            </div>
          </AdminForm>
          <button type="button" onClick={() => setEditing(null)} className="mt-2 text-xs font-bold text-ink-500 hover:underline">Закрыть</button>
        </div>
      ) : null}
    </div>
  );
}
