"use client";

import { useActionState, useMemo, useState } from "react";
import Link from "next/link";
import { AlertTriangle, ArrowRight, CheckCircle2, ExternalLink, Loader2 } from "lucide-react";
import { useFormStatus } from "react-dom";
import { Checkbox, F, FormContext, inputCls, selectCls } from "@/components/admin/form";
import { HtmlEditor } from "@/components/admin/html-editor";
import { ImageField } from "@/components/admin/image-field";
import { saveTour } from "@/server/actions/admin/tours";
import { SEASON_LABELS, TOUR_TYPE_LABELS } from "@/config/site";
import type { FormState } from "@/lib/validation";
import { ShiftsEditor, type ShiftDraft } from "./shifts-editor";
import { GalleryEditor, type ImageDraft } from "./gallery-editor";

export type TourFormData = {
  id?: number; title: string; slug: string; type: string; regionId: number | null; short: string; address: string; ageText: string; ageMin: number | null; ageMax: number | null;
  website: string | null; priceText: string | null; priceMin: number | null; cover: string | null; seasons: string[]; seasonText: string | null; isBest: boolean; isHit: boolean;
  status: "draft" | "published" | "archived"; internalNote: string | null; sortOrder: number;
  seoTitle: string | null; metaDescription: string | null; metaKeywords: string | null;
  tagIds: number[]; shifts: ShiftDraft[]; images: ImageDraft[]; sections: Record<string, { title: string; html: string }>; documents: { label: string; url: string }[];
};
type Props = { tour: TourFormData; regions: { id: number; name: string }[]; tags: { id: number; label: string }[] };

const TABS = [["basic", "1. Основное"], ["shifts", "2. Смены и цены"], ["photos", "3. Фото"], ["content", "4. Описание"], ["docs", "5. Документы"], ["publish", "6. Публикация и SEO"]] as const;
type Tab = (typeof TABS)[number][0];
const SECTIONS: [string, string, string][] = [
  ["description", "Описание", "Расскажите о месте, программе, питании, безопасности. Первый абзац виден в карточке."],
  ["residence", "Проживание", "Корпуса, номера, удобства, инфраструктура."],
  ["programm", "Программа", "Чем дети занимаются: кружки, спорт, экскурсии, мероприятия."],
  ["payment_smeny", "Стоимость смен", "Что входит в стоимость и что оплачивается отдельно."],
  ["otpravka_children", "Организованная отправка детей", "Как добраться: поезд, автобус, сопровождение, место сбора."],
  ["for_parents", "Документы", "Какие документы и вещи нужно подготовить."],
  ["for_contraindications", "Противопоказания", "Медицинские ограничения для заезда."],
];
const TEMPLATES: Record<string, string> = {
  payment_smeny: "<p><strong>В стоимость путёвки входит:</strong></p><ul><li>проживание;</li><li>5-разовое питание;</li><li>программа лагеря;</li><li>пользование инфраструктурой;</li><li>медицинское сопровождение;</li><li>страховка.</li></ul><p><strong>Оплачивается дополнительно:</strong></p><ul><li>трансфер;</li><li>экскурсии.</li></ul>",
  for_parents: "<p><strong>В лагерь необходимо подготовить:</strong></p><ul><li>медицинскую справку по форме 079/у;</li><li>справку об эпидокружении (за 3 дня до заезда);</li><li>копию свидетельства о рождении (паспорта с 14 лет);</li><li>копию полиса ОМС;</li><li>согласие на медицинское вмешательство.</li></ul><p><strong>В дорогу:</strong></p><ul><li>оригинал свидетельства о рождении или паспорта.</li></ul>",
  for_contraindications: "<ul><li>все заболевания в остром периоде;</li><li>бронхиальная астма средней и тяжёлой степени;</li><li>сахарный диабет;</li><li>эпилепсия;</li><li>инфекционные и паразитарные заболевания.</li></ul>",
};

function SaveBar({ tour, warnings }: { tour: TourFormData; warnings: string[] }) {
  const { pending } = useFormStatus();
  return (
    <div className="sticky top-0 z-30 -mx-4 mb-4 flex flex-wrap items-center gap-3 border-b border-ink-100 bg-ink-50/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
      <div className="min-w-0 flex-1">
        <div className="truncate text-lg font-black text-ink-900">{tour.title || "Новый тур"}</div>
        <div className="text-xs text-ink-500">{warnings.length ? <span className="text-coral-600"><AlertTriangle className="mr-1 inline h-3.5 w-3.5" />{warnings.join(" · ")}</span> : <span className="text-mint-700"><CheckCircle2 className="mr-1 inline h-3.5 w-3.5" />Всё необходимое заполнено</span>}</div>
      </div>
      {tour.id ? <Link href={`/tour/${tour.slug}/`} target="_blank" className="btn-ghost !py-2 !text-xs"><ExternalLink className="h-4 w-4" /> На сайте</Link> : null}
      <button type="submit" disabled={pending} className="btn-primary !py-2.5">{pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}{tour.id ? "Сохранить" : "Создать тур"}</button>
    </div>
  );
}

export function TourForm({ tour, regions, tags }: Props) {
  const [state, action] = useActionState(saveTour, { ok: false } as FormState);
  const [tab, setTab] = useState<Tab>("basic");
  const [title, setTitle] = useState(tour.title);
  const [shifts, setShifts] = useState<ShiftDraft[]>(tour.shifts);
  const [images, setImages] = useState<ImageDraft[]>(tour.images);
  const [cover, setCover] = useState<string | null>(tour.cover);
  const [documents, setDocuments] = useState(tour.documents);
  const [sections, setSections] = useState<Record<string, string>>(Object.fromEntries(SECTIONS.map(([k]) => [k, tour.sections[k]?.html ?? ""])));
  const [status, setStatus] = useState(tour.status);

  const warnings = useMemo(() => [
    !title.trim() && "нет названия",
    !cover && !images.length && "нет фото",
    !shifts.length && "нет смен",
    shifts.length > 0 && !shifts.some((s) => s.price) && "нет цен",
    !sections.description?.trim() && "нет описания",
  ].filter(Boolean) as string[], [title, cover, images.length, shifts, sections.description]);

  const next = () => { const i = TABS.findIndex(([k]) => k === tab); if (i < TABS.length - 1) { setTab(TABS[i + 1][0]); window.scrollTo({ top: 0 }); } };
  const nextBtn = tab !== "publish" ? <button type="button" onClick={next} className="btn-outline mt-6 !py-2 !text-xs">Далее: {TABS[TABS.findIndex(([k]) => k === tab) + 1][1].slice(3)} <ArrowRight className="h-4 w-4" /></button> : null;
  const panel = (k: Tab) => ({ hidden: tab !== k, className: "rounded-2xl bg-white p-5 shadow-card sm:p-6" });
  const errFields = Object.keys(state.errors ?? {});

  return (
    <form action={action}>
      <FormContext.Provider value={state}>
      {tour.id ? <input type="hidden" name="id" value={tour.id} /> : null}
      <input type="hidden" name="shifts" value={JSON.stringify(shifts)} />
      <input type="hidden" name="images" value={JSON.stringify(images)} />
      <input type="hidden" name="documents" value={JSON.stringify(documents)} />
      {SECTIONS.map(([k]) => <input key={k} type="hidden" name={`section_${k}`} value={sections[k] ?? ""} />)}
      <SaveBar tour={{ ...tour, title }} warnings={warnings} />
      {state.message ? <div role={state.ok ? "status" : "alert"} className={`mb-4 rounded-xl px-4 py-2.5 text-sm font-semibold ${state.ok ? "bg-mint-100 text-mint-700" : "bg-coral-100 text-coral-700"}`}>{state.message}{errFields.length ? ` (${errFields.join(", ")})` : ""}</div> : null}

      <div role="tablist" className="no-scrollbar mb-4 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-card">
        {TABS.map(([k, l]) => (
          <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={`shrink-0 rounded-xl px-4 py-2 text-sm font-extrabold transition ${tab === k ? "bg-brand-500 text-white" : "text-ink-600 hover:bg-brand-50"}`}>
            {l}{k === "shifts" && shifts.length ? <span className="ml-1 opacity-70">({shifts.length})</span> : null}{k === "photos" && images.length ? <span className="ml-1 opacity-70">({images.length})</span> : null}
          </button>
        ))}
      </div>

      {/* 1. basic */}
      <section {...panel("basic")}>
        <div className="grid gap-4 sm:grid-cols-2">
          <F label="Название тура" name="title" className="sm:col-span-2"><input name="title" value={title} onChange={(e) => setTitle(e.target.value)} required className={inputCls} placeholder="Например: ДОЛ «Морская звезда», лето 2027" /></F>
          <F label="Тип" name="type"><select name="type" defaultValue={tour.type} className={selectCls}>{Object.entries(TOUR_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></F>
          <F label="Направление (регион)" name="regionId"><select name="regionId" defaultValue={tour.regionId ?? ""} className={selectCls}><option value="">—</option>{regions.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}</select></F>
          <F label="Адрес / место" name="address" className="sm:col-span-2"><input name="address" defaultValue={tour.address} className={inputCls} placeholder="Крым, г. Саки, ул. Морская, 11" /></F>
          <F label="Краткое описание для карточки" name="short" className="sm:col-span-2" hint="1–2 предложения: что это и для кого."><textarea name="short" defaultValue={tour.short} rows={2} className={inputCls} /></F>
          <div className="grid grid-cols-3 gap-3">
            <F label="Возраст от" name="ageMin"><input name="ageMin" type="number" min={0} defaultValue={tour.ageMin ?? ""} className={inputCls} /></F>
            <F label="до" name="ageMax"><input name="ageMax" type="number" min={0} defaultValue={tour.ageMax ?? ""} className={inputCls} /></F>
            <F label="Текст" name="ageText" hint="напр. 7–16 лет"><input name="ageText" defaultValue={tour.ageText} className={inputCls} /></F>
          </div>
          <div>
            <span className="label">Сезоны</span>
            <div className="flex flex-wrap gap-3 pt-2">{Object.entries(SEASON_LABELS).map(([k, v]) => <Checkbox key={k} name="seasons[]" value={k} label={v} defaultChecked={tour.seasons.includes(k)} />)}</div>
            <input name="seasonText" defaultValue={tour.seasonText ?? ""} className={`${inputCls} mt-2`} placeholder="Подпись: лето 2027" />
          </div>
          <F label="Сайт лагеря" name="website"><input name="website" defaultValue={tour.website ?? ""} className={inputCls} placeholder="https://" /></F>
          <div>
            <span className="label">Характеристики</span>
            <div className="grid max-h-44 grid-cols-2 gap-1.5 overflow-y-auto rounded-xl border border-ink-100 p-3 sm:grid-cols-3">{tags.map((t) => <Checkbox key={t.id} name="tags[]" value={t.id} label={t.label} defaultChecked={tour.tagIds.includes(t.id)} />)}</div>
          </div>
        </div>
      </section>

      {/* 2. shifts */}
      <section {...panel("shifts")}>
        <p className="mb-4 text-sm text-ink-500">Заезды с датами, ценой и наличием мест. Название формируется автоматически из дат. Группа нужна, если у лагеря несколько корпусов или программ с разными ценами.</p>
        <ShiftsEditor value={shifts} onChange={setShifts} />
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <F label="Цена «от», ₽" name="priceMin" hint="Используется, только если у смен нет цен; иначе берётся минимум по сменам"><input name="priceMin" type="number" defaultValue={tour.priceMin ?? ""} className={inputCls} /></F>
          <F label="Текст цены" name="priceText" hint="Например: от 1 200 $ или по запросу"><input name="priceText" defaultValue={tour.priceText ?? ""} className={inputCls} /></F>
        </div>
      </section>

      {/* 3. photos */}
      <section {...panel("photos")}>
        <GalleryEditor images={images} cover={cover} onChange={setImages} onCover={setCover} />
        <div className="mt-6 max-w-md"><ImageField name="cover" value={cover ?? ""} onChange={(u) => setCover(u || null)} label="Обложка" hint="Обычно достаточно нажать ★ на фото в галерее" /></div>
      </section>

      {/* 4. content */}
      <section {...panel("content")}>
        <div className="space-y-8">
          {SECTIONS.map(([key, label, hint]) => (
            <div key={key}>
              <div className="mb-2 flex flex-wrap items-center gap-3">
                <span className="text-base font-extrabold text-ink-900">{label}</span>
                <input name={`section_title_${key}`} defaultValue={tour.sections[key]?.title ?? label} className="input !w-64 !py-1 !text-xs" aria-label="Название вкладки на сайте" />
                {TEMPLATES[key] && !sections[key]?.trim() ? <button type="button" onClick={() => setSections({ ...sections, [key]: TEMPLATES[key] })} className="btn-ghost !py-1 !text-xs">Вставить шаблон</button> : null}
                <span className="text-xs text-ink-400">{hint}</span>
              </div>
              <HtmlEditor key={`${key}-${sections[key] === TEMPLATES[key] ? "t" : "u"}`} name={`_${key}`} defaultValue={sections[key]} rows={key === "description" ? 14 : 7} onChange={(html) => setSections((s) => ({ ...s, [key]: html }))} />
            </div>
          ))}
        </div>
      </section>

      {/* 5. docs */}
      <section {...panel("docs")}>
        <p className="mb-4 text-sm text-ink-500">Файлы для скачивания родителями: анкеты, формы справок, договор. Загрузите PDF через поле ниже или укажите ссылку.</p>
        <div className="space-y-2">
          {documents.map((d, i) => (
            <div key={i} className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
              <input value={d.label} onChange={(e) => setDocuments(documents.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} placeholder="Название (Медицинская справка)" className="input !py-2 !text-sm" />
              <input value={d.url} onChange={(e) => setDocuments(documents.map((x, j) => (j === i ? { ...x, url: e.target.value } : x)))} placeholder="/uploads/… или https://" className="input !py-2 !text-sm" />
              <button type="button" onClick={() => setDocuments(documents.filter((_, j) => j !== i))} className="btn-ghost !px-3 !py-2 !text-xs text-coral-600">Удалить</button>
            </div>
          ))}
        </div>
        <button type="button" onClick={() => setDocuments([...documents, { label: "", url: "" }])} className="btn-outline mt-3 !py-2 !text-xs">+ Добавить документ</button>
      </section>

      {/* 6. publish & seo */}
      <section {...panel("publish")}>
        <div className="grid gap-6 lg:grid-cols-2">
          <div className="space-y-4">
            <div>
              <span className="label">Статус</span>
              <div className="flex flex-wrap gap-2">
                {([["draft", "Черновик", "не виден на сайте"], ["published", "Опубликован", "виден в каталоге и на главной"], ["archived", "Архив", "скрыт, хранится для копирования"]] as const).map(([k, l, h]) => (
                  <label key={k} className={`cursor-pointer rounded-xl border-2 px-4 py-2 ${status === k ? "border-brand-500 bg-brand-50" : "border-ink-200"}`}>
                    <input type="radio" name="status" value={k} checked={status === k} onChange={() => setStatus(k)} className="mr-2 accent-brand-500" /><span className="font-extrabold">{l}</span><span className="ml-1 text-xs text-ink-500">{h}</span>
                  </label>
                ))}
              </div>
            </div>
            <div className="flex flex-wrap gap-4">
              <Checkbox name="isBest" label="★ Лучшее предложение (блок на главной)" defaultChecked={tour.isBest} />
              <Checkbox name="isHit" label="🔥 Хит продаж" defaultChecked={tour.isHit} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <F label="Порядок в каталоге" name="sortOrder" hint="меньше — выше"><input name="sortOrder" type="number" defaultValue={tour.sortOrder} className={inputCls} /></F>
              <F label="URL (slug)" name="slug" hint="Пусто — из названия"><input name="slug" defaultValue={tour.slug} className={inputCls} /></F>
            </div>
            <F label="Заметка для менеджеров (не видна на сайте)" name="internalNote"><textarea name="internalNote" defaultValue={tour.internalNote ?? ""} rows={3} className={inputCls} placeholder="Контакты лагеря, условия комиссии, что уточнить…" /></F>
          </div>
          <div className="space-y-3">
            <div className="text-sm font-extrabold text-ink-900">SEO</div>
            <F label="Title страницы" name="seoTitle" hint="{year} подставит текущий год. Пусто — название тура."><input name="seoTitle" defaultValue={tour.seoTitle ?? ""} className={inputCls} /></F>
            <F label="Meta description" name="metaDescription" hint="Пусто — краткое описание."><textarea name="metaDescription" defaultValue={tour.metaDescription ?? ""} rows={3} className={inputCls} /></F>
            <F label="Keywords" name="metaKeywords"><input name="metaKeywords" defaultValue={tour.metaKeywords ?? ""} className={inputCls} /></F>
          </div>
        </div>
      </section>
      {nextBtn}
      </FormContext.Provider>
    </form>
  );
}
