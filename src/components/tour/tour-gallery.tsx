"use client";

import { useCallback, useEffect, useState } from "react";
import { ChevronLeft, ChevronRight, Images, X } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";

type Props = { images: { url: string; alt?: string | null }[]; title: string };

export function TourGallery({ images, title }: Props) {
  const [index, setIndex] = useState(0);
  const [open, setOpen] = useState(false);
  const list = images.length ? images : [{ url: "", alt: title }];
  const prev = useCallback(() => setIndex((i) => (i - 1 + list.length) % list.length), [list.length]);
  const next = useCallback(() => setIndex((i) => (i + 1) % list.length), [list.length]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
      if (e.key === "ArrowLeft") prev();
      if (e.key === "ArrowRight") next();
    };
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => { window.removeEventListener("keydown", onKey); document.body.style.overflow = ""; };
  }, [open, prev, next]);

  const current = list[index];
  return (
    <div>
      <div className="relative aspect-[3/2] overflow-hidden rounded-3xl bg-brand-50 shadow-card">
        <button type="button" onClick={() => setOpen(true)} className="absolute inset-0 h-full w-full" aria-label="Открыть фото на весь экран">
          <SafeImage src={current.url} alt={current.alt ?? title} fill sizes="(max-width: 1024px) 100vw, 60vw" className="object-cover" preload />
        </button>
        {list.length > 1 ? (
          <>
            <button type="button" onClick={prev} aria-label="Предыдущее фото" className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-ink-800 shadow-card hover:bg-white"><ChevronLeft className="h-5 w-5" /></button>
            <button type="button" onClick={next} aria-label="Следующее фото" className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-ink-800 shadow-card hover:bg-white"><ChevronRight className="h-5 w-5" /></button>
            <div className="absolute bottom-3 right-3 rounded-full bg-ink-900/70 px-3 py-1 text-xs font-bold text-white"><Images className="mr-1 inline h-3.5 w-3.5" />{index + 1} / {list.length}</div>
          </>
        ) : null}
      </div>
      {list.length > 1 ? (
        <div className="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
          {list.map((img, i) => (
            <button key={i} type="button" onClick={() => setIndex(i)} aria-label={`Фото ${i + 1}`} aria-current={i === index} className={`relative h-16 w-24 shrink-0 overflow-hidden rounded-xl ring-2 transition ${i === index ? "ring-brand-500" : "ring-transparent hover:ring-brand-200"}`}>
              <SafeImage src={img.url} alt="" fill sizes="96px" className="object-cover" />
            </button>
          ))}
        </div>
      ) : null}
      {open ? (
        <div role="dialog" aria-modal="true" aria-label="Галерея" className="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/95 p-4" onClick={() => setOpen(false)}>
          <button type="button" onClick={() => setOpen(false)} aria-label="Закрыть" className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"><X className="h-6 w-6" /></button>
          <div className="relative h-full w-full max-w-6xl" onClick={(e) => e.stopPropagation()}>
            <SafeImage src={current.url} alt={current.alt ?? title} fill sizes="100vw" className="object-contain" quality={85} />
            {list.length > 1 ? (
              <>
                <button type="button" onClick={prev} aria-label="Предыдущее фото" className="absolute left-0 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"><ChevronLeft className="h-7 w-7" /></button>
                <button type="button" onClick={next} aria-label="Следующее фото" className="absolute right-0 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"><ChevronRight className="h-7 w-7" /></button>
              </>
            ) : null}
            <div className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-bold text-white">{index + 1} / {list.length}</div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
