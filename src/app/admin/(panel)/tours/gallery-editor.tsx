"use client";

import { useState } from "react";
import Image from "next/image";
import { ArrowLeft, ArrowRight, Loader2, Star, Trash2, Upload } from "lucide-react";

export type ImageDraft = { url: string; alt: string | null };

export function GalleryEditor({ images, cover, onChange, onCover }: { images: ImageDraft[]; cover: string | null; onChange: (v: ImageDraft[]) => void; onCover: (url: string) => void }) {
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const move = (i: number, d: -1 | 1) => { const j = i + d; if (j < 0 || j >= images.length) return; const c = [...images]; [c[i], c[j]] = [c[j], c[i]]; onChange(c); };

  async function upload(files: FileList) {
    const list = Array.from(files);
    setProgress({ done: 0, total: list.length });
    const added: ImageDraft[] = [];
    for (const [i, file] of list.entries()) {
      const body = new FormData(); body.set("file", file);
      try {
        const res = await fetch("/api/admin/upload/", { method: "POST", body });
        const data = (await res.json()) as { url?: string; error?: string };
        if (data.url) added.push({ url: data.url, alt: null }); else alert(`${file.name}: ${data.error ?? "ошибка загрузки"}`);
      } catch { alert(`${file.name}: ошибка загрузки`); }
      setProgress({ done: i + 1, total: list.length });
    }
    const next = [...images, ...added];
    onChange(next);
    if (!cover && next[0]) onCover(next[0].url);
    setProgress(null);
  }

  return (
    <div>
      <label
        onDragOver={(e) => e.preventDefault()}
        onDrop={(e) => { e.preventDefault(); if (e.dataTransfer.files.length) void upload(e.dataTransfer.files); }}
        className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brand-200 bg-brand-50/40 px-4 py-8 text-center text-sm font-bold text-brand-700 hover:bg-brand-50"
      >
        {progress ? <><Loader2 className="h-6 w-6 animate-spin" /> Загружаем {progress.done} из {progress.total}…</> : <><Upload className="h-6 w-6" /> Перетащите фотографии сюда или нажмите, чтобы выбрать<span className="text-xs font-semibold text-ink-400">JPG, PNG, WebP до 15 МБ. Фото сжимаются автоматически.</span></>}
        <input type="file" accept="image/*" multiple className="hidden" onChange={(e) => { if (e.target.files?.length) void upload(e.target.files); e.target.value = ""; }} />
      </label>
      {images.length ? (
        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          {images.map((im, i) => {
            const isCover = im.url === cover;
            return (
              <figure key={im.url + i} className={`overflow-hidden rounded-xl bg-white shadow-card ring-2 ${isCover ? "ring-sun-400" : "ring-transparent"}`}>
                <div className="relative aspect-[4/3] bg-ink-100"><Image src={im.url} alt="" fill sizes="240px" className="object-cover" />
                  {isCover ? <span className="absolute left-2 top-2 rounded-full bg-sun-300 px-2 py-0.5 text-[10px] font-black text-ink-900">ОБЛОЖКА</span> : null}
                </div>
                <div className="flex items-center gap-0.5 p-1.5">
                  <button type="button" onClick={() => move(i, -1)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-50" aria-label="Левее"><ArrowLeft className="h-4 w-4" /></button>
                  <button type="button" onClick={() => move(i, 1)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-50" aria-label="Правее"><ArrowRight className="h-4 w-4" /></button>
                  <button type="button" onClick={() => onCover(im.url)} disabled={isCover} className="rounded-lg p-1.5 text-sun-600 hover:bg-sun-50 disabled:opacity-40" aria-label="Сделать обложкой" title="Сделать обложкой"><Star className="h-4 w-4" /></button>
                  <button type="button" onClick={() => onChange(images.filter((_, j) => j !== i))} className="ml-auto rounded-lg p-1.5 text-coral-600 hover:bg-coral-50" aria-label="Удалить"><Trash2 className="h-4 w-4" /></button>
                </div>
                <input value={im.alt ?? ""} onChange={(e) => onChange(images.map((x, j) => (j === i ? { ...x, alt: e.target.value || null } : x)))} placeholder="Подпись (alt)" className="w-full border-t border-ink-100 px-2 py-1.5 text-xs outline-none" />
              </figure>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
