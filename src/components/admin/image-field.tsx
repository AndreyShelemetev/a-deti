"use client";

import { useState } from "react";
import Image from "next/image";
import { Loader2, Upload, X } from "lucide-react";

type Props = { name: string; defaultValue?: string | null; label?: string; hint?: string };

/** Single image field: shows a preview, uploads a new file through /api/admin/upload, stores the URL in a hidden input. */
export function ImageField({ name, defaultValue, label = "Изображение", hint }: Props) {
  const [url, setUrl] = useState(defaultValue ?? "");
  const [busy, setBusy] = useState(false);
  async function upload(file: File) {
    setBusy(true);
    try {
      const fd = new FormData(); fd.set("file", file);
      const res = await fetch("/api/admin/upload/", { method: "POST", body: fd });
      const data = (await res.json()) as { url?: string; error?: string };
      if (data.url) setUrl(data.url); else alert(data.error ?? "Ошибка загрузки");
    } finally { setBusy(false); }
  }
  return (
    <div>
      <span className="label">{label}</span>
      <input type="hidden" name={name} value={url} />
      <div className="flex items-start gap-3">
        <div className="relative h-24 w-36 shrink-0 overflow-hidden rounded-xl bg-ink-100">
          {url ? <Image src={url} alt="" fill sizes="144px" className="object-cover" unoptimized={url.endsWith(".svg")} /> : <span className="flex h-full items-center justify-center text-xs text-ink-400">нет фото</span>}
        </div>
        <div className="space-y-2">
          <label className="btn-outline !py-2 !text-xs cursor-pointer">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />} Загрузить
            <input type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); e.target.value = ""; }} />
          </label>
          {url ? <button type="button" onClick={() => setUrl("")} className="ml-2 inline-flex items-center gap-1 text-xs font-bold text-coral-600"><X className="h-3.5 w-3.5" /> Убрать</button> : null}
          <input type="text" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="/uploads/… или https://…" className="input !py-1.5 !text-xs" aria-label="URL изображения" />
          {hint ? <p className="text-xs text-ink-400">{hint}</p> : null}
        </div>
      </div>
    </div>
  );
}
