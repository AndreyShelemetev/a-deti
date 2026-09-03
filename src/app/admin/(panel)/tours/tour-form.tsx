"use client";

import { useState } from "react";
import Image from "next/image";
import { ArrowDown, ArrowUp, Plus, Trash2, Upload } from "lucide-react";
import { AdminForm, Checkbox, F, inputCls, selectCls } from "@/components/admin/form";
import { HtmlEditor } from "@/components/admin/html-editor";
import { ImageField } from "@/components/admin/image-field";
import { saveTour } from "@/server/actions/admin/tours";
import { SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";

export type TourFormData = {
  id?: number; title: string; slug: string; type: string; regionId: number | null; short: string; address: string; ageText: string; ageMin: number | null; ageMax: number | null;
  website: string | null; priceText: string | null; priceMin: number | null; cover: string | null; seasons: string[]; seasonText: string | null; isBest: boolean; isHit: boolean; isPublished: boolean; sortOrder: number;
  seoTitle: string | null; metaDescription: string | null; metaKeywords: string | null;
  tagIds: number[];
  shifts: { group: string | null; name: string; price: number | null; startDate: string | null; endDate: string | null }[];
  images: { url: string; alt: string | null }[];
  sections: Record<string, { title: string; html: string }>;
  documents: { label: string; url: string }[];
};
type Props = { tour: TourFormData; regions: { id: number; name: string }[]; tags: { id: number; label: string }[] };

const SECTIONS: [string, string][] = [["description", "Описание"], ["residence", "Проживание"], ["programm", "Программа"], ["payment_smeny", "Стоимость смен"], ["otpravka_children", "Организованная отправка детей"], ["for_parents", "Документы"], ["for_contraindications", "Противопоказания"]];

export function TourForm({ tour, regions, tags }: Props) {
  const [shifts, setShifts] = useState(tour.shifts);
  const [images, setImages] = useState(tour.images);
  const [documents, setDocuments] = useState(tour.documents);
  const [uploading, setUploading] = useState(false);

  async function uploadFiles(files: FileList) {
    setUploading(true);
    try {
      const added: { url: string; alt: string | null }[] = [];
      for (const file of Array.from(files)) {
        const body = new FormData(); body.set("file", file);
        const res = await fetch("/api/admin/upload/", { method: "POST", body });
        const data = (await res.json()) as { url?: string };
        if (data.url) added.push({ url: data.url, alt: null });
      }
      setImages((prev) => [...prev, ...added]);
    } finally { setUploading(false); }
  }
  const move = <T,>(arr: T[], i: number, dir: -1 | 1) => { const j = i + dir; if (j < 0 || j >= arr.length) return arr; const copy = [...arr]; [copy[i], copy[j]] = [copy[j], copy[i]]; return copy; };

  return (
    <AdminForm action={saveTour} submitLabel={tour.id ? "Сохранить тур" : "Создать тур"}>
      {tour.id ? <input type="hidden" name="id" value={tour.id} /> : null}
      <input type="hidden" name="shifts" value={JSON.stringify(shifts)} />
      <input type="hidden" name="images" value={JSON.stringify(images)} />
      <input type="hidden" name="documents" value={JSON.stringify(documents)} />

      <div className="grid gap-6 xl:grid-cols-12">
        <div className="space-y-5 xl:col-span-8">
          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Основное</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <F label="Название" name="title" className="sm:col-span-2"><input name="title" defaultValue={tour.title} required className={inputCls} /></F>
              <F label="URL (slug)" name="slug" hint="Пусто — сформируется из названия. Меняйте осторожно: старый адрес перестанет работать."><input name="slug" defaultValue={tour.slug} className={inputCls} /></F>
              <F label="Тип" name="type"><select name="type" defaultValue={tour.type} className={selectCls}>{Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></F>
              <F label="Регион" name="regionId"><select name="regionId" defaultValue={tour.regionId ?? ""} className={selectCls}><option value="">—</option>{regions.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}</select></F>
              <F label="Адрес / место" name="address"><input name="address" defaultValue={tour.address} className={inputCls} /></F>
              <F label="Краткое описание (для карточки)" name="short" className="sm:col-span-2"><textarea name="short" defaultValue={tour.short} rows={3} className={inputCls} /></F>
              <F label="Возраст (текст)" name="ageText" hint="Например: от 7 до 16 лет"><input name="ageText" defaultValue={tour.ageText} className={inputCls} /></F>
              <div className="grid grid-cols-2 gap-3">
                <F label="Возраст от" name="ageMin"><input name="ageMin" type="number" defaultValue={tour.ageMin ?? ""} className={inputCls} /></F>
                <F label="Возраст до" name="ageMax"><input name="ageMax" type="number" defaultValue={tour.ageMax ?? ""} className={inputCls} /></F>
              </div>
              <F label="Сайт лагеря" name="website"><input name="website" defaultValue={tour.website ?? ""} className={inputCls} placeholder="https://" /></F>
              <F label="Сезоны (текст)" name="seasonText" hint="Например: лето 2027"><input name="seasonText" defaultValue={tour.seasonText ?? ""} className={inputCls} /></F>
              <div className="sm:col-span-2">
                <span className="label">Сезоны</span>
                <div className="flex flex-wrap gap-4">{Object.entries(SEASON_LABELS).map(([k, v]) => <Checkbox key={k} name="seasons[]" value={k} label={v} defaultChecked={tour.seasons.includes(k)} />)}</div>
              </div>
            </div>
          </section>

          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-1 text-lg font-extrabold">Смены и цены</h2>
            <p className="mb-4 text-xs text-ink-500">Группа — корпус или программа (например, дружины «Орлёнка»). Цена в рублях; пусто — «по запросу».</p>
            <div className="space-y-2">
              {shifts.map((s, i) => (
                <div key={i} className="grid items-center gap-2 rounded-xl bg-ink-50 p-2 sm:grid-cols-[1fr_2fr_1fr_1fr_1fr_auto]">
                  <input value={s.group ?? ""} onChange={(e) => setShifts(shifts.map((x, j) => (j === i ? { ...x, group: e.target.value || null } : x)))} placeholder="Группа" className="input !py-1.5 !text-xs" />
                  <input value={s.name} onChange={(e) => setShifts(shifts.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))} placeholder="1 смена 01.06–21.06 [21 день]" className="input !py-1.5 !text-xs" />
                  <input type="number" value={s.price ?? ""} onChange={(e) => setShifts(shifts.map((x, j) => (j === i ? { ...x, price: e.target.value ? Number(e.target.value) : null } : x)))} placeholder="Цена" className="input !py-1.5 !text-xs" />
                  <input type="date" value={s.startDate ?? ""} onChange={(e) => setShifts(shifts.map((x, j) => (j === i ? { ...x, startDate: e.target.value || null } : x)))} className="input !py-1.5 !text-xs" />
                  <input type="date" value={s.endDate ?? ""} onChange={(e) => setShifts(shifts.map((x, j) => (j === i ? { ...x, endDate: e.target.value || null } : x)))} className="input !py-1.5 !text-xs" />
                  <div className="flex gap-1">
                    <button type="button" onClick={() => setShifts(move(shifts, i, -1))} className="rounded-lg p-1.5 hover:bg-white" aria-label="Выше"><ArrowUp className="h-4 w-4" /></button>
                    <button type="button" onClick={() => setShifts(move(shifts, i, 1))} className="rounded-lg p-1.5 hover:bg-white" aria-label="Ниже"><ArrowDown className="h-4 w-4" /></button>
                    <button type="button" onClick={() => setShifts(shifts.filter((_, j) => j !== i))} className="rounded-lg p-1.5 text-coral-600 hover:bg-white" aria-label="Удалить"><Trash2 className="h-4 w-4" /></button>
                  </div>
                </div>
              ))}
            </div>
            <button type="button" onClick={() => setShifts([...shifts, { group: null, name: "", price: null, startDate: null, endDate: null }])} className="btn-outline mt-3 !py-2 !text-xs"><Plus className="h-4 w-4" /> Добавить смену</button>
          </section>

          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Фотогалерея</h2>
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
              {images.map((im, i) => (
                <div key={im.url + i} className="group relative aspect-[4/3] overflow-hidden rounded-xl bg-ink-100">
                  <Image src={im.url} alt="" fill sizes="160px" className="object-cover" />
                  <div className="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-ink-900/60 p-1 opacity-0 transition group-hover:opacity-100">
                    <button type="button" onClick={() => setImages(move(images, i, -1))} className="rounded p-1 text-white hover:bg-white/20" aria-label="Левее"><ArrowUp className="h-3.5 w-3.5 -rotate-90" /></button>
                    <button type="button" onClick={() => setImages(move(images, i, 1))} className="rounded p-1 text-white hover:bg-white/20" aria-label="Правее"><ArrowDown className="h-3.5 w-3.5 -rotate-90" /></button>
                    <button type="button" onClick={() => setImages(images.filter((_, j) => j !== i))} className="rounded p-1 text-white hover:bg-coral-500" aria-label="Удалить"><Trash2 className="h-3.5 w-3.5" /></button>
                  </div>
                </div>
              ))}
              <label className="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-brand-200 text-xs font-bold text-brand-600 hover:bg-brand-50">
                <Upload className="h-5 w-5" /> {uploading ? "Загрузка…" : "Добавить фото"}
                <input type="file" accept="image/*" multiple className="hidden" onChange={(e) => { if (e.target.files?.length) void uploadFiles(e.target.files); e.target.value = ""; }} />
              </label>
            </div>
          </section>

          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Вкладки карточки тура</h2>
            <div className="space-y-6">
              {SECTIONS.map(([key, title]) => (
                <div key={key}>
                  <div className="mb-2 flex items-center gap-3"><span className="text-sm font-extrabold text-ink-900">{title}</span><input name={`section_title_${key}`} defaultValue={tour.sections[key]?.title ?? title} className="input !w-72 !py-1 !text-xs" aria-label="Название вкладки" /></div>
                  <HtmlEditor name={`section_${key}`} defaultValue={tour.sections[key]?.html ?? ""} rows={key === "description" ? 16 : 8} />
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Документы для скачивания</h2>
            <div className="space-y-2">
              {documents.map((d, i) => (
                <div key={i} className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                  <input value={d.label} onChange={(e) => setDocuments(documents.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} placeholder="Название" className="input !py-1.5 !text-xs" />
                  <input value={d.url} onChange={(e) => setDocuments(documents.map((x, j) => (j === i ? { ...x, url: e.target.value } : x)))} placeholder="/uploads/… или https://" className="input !py-1.5 !text-xs" />
                  <button type="button" onClick={() => setDocuments(documents.filter((_, j) => j !== i))} className="rounded-lg p-1.5 text-coral-600 hover:bg-ink-50" aria-label="Удалить"><Trash2 className="h-4 w-4" /></button>
                </div>
              ))}
            </div>
            <button type="button" onClick={() => setDocuments([...documents, { label: "", url: "" }])} className="btn-outline mt-3 !py-2 !text-xs"><Plus className="h-4 w-4" /> Добавить документ</button>
          </section>
        </div>

        <div className="space-y-5 xl:col-span-4">
          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Публикация</h2>
            <div className="space-y-3">
              <Checkbox name="isPublished" label="Опубликован на сайте" defaultChecked={tour.isPublished} />
              <Checkbox name="isBest" label="★ Лучшее предложение (главная)" defaultChecked={tour.isBest} />
              <Checkbox name="isHit" label="🔥 Хит продаж" defaultChecked={tour.isHit} />
              <F label="Сортировка (меньше — выше)" name="sortOrder"><input name="sortOrder" type="number" defaultValue={tour.sortOrder} className={inputCls} /></F>
            </div>
          </section>
          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Обложка и цена</h2>
            <ImageField name="cover" defaultValue={tour.cover} label="Обложка" hint="Если пусто — первое фото галереи" />
            <div className="mt-4 grid grid-cols-2 gap-3">
              <F label="Цена от, ₽" name="priceMin" hint="Пусто — минимум по сменам"><input name="priceMin" type="number" defaultValue={tour.priceMin ?? ""} className={inputCls} /></F>
              <F label="Текст цены" name="priceText" hint="Например: от 1 200 $"><input name="priceText" defaultValue={tour.priceText ?? ""} className={inputCls} /></F>
            </div>
          </section>
          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">Характеристики</h2>
            <div className="grid max-h-80 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-1">
              {tags.map((t) => <Checkbox key={t.id} name="tags[]" value={t.id} label={t.label} defaultChecked={tour.tagIds.includes(t.id)} />)}
            </div>
          </section>
          <section className="rounded-2xl bg-white p-5 shadow-card">
            <h2 className="mb-4 text-lg font-extrabold">SEO</h2>
            <div className="space-y-3">
              <F label="Title" name="seoTitle" hint="{year} подставит текущий год"><input name="seoTitle" defaultValue={tour.seoTitle ?? ""} className={inputCls} /></F>
              <F label="Meta description" name="metaDescription"><textarea name="metaDescription" defaultValue={tour.metaDescription ?? ""} rows={3} className={inputCls} /></F>
              <F label="Keywords" name="metaKeywords"><input name="metaKeywords" defaultValue={tour.metaKeywords ?? ""} className={inputCls} /></F>
            </div>
          </section>
        </div>
      </div>
    </AdminForm>
  );
}
