"use client";

import { useRef, useState } from "react";
import { Bold, Heading2, Heading3, Image as ImageIcon, Link2, List, Eye, Code, Loader2 } from "lucide-react";

type Props = { name: string; defaultValue?: string; rows?: number; label?: string };

/**
 * Lightweight HTML editor: toolbar inserts semantic tags, live preview renders the result with site typography.
 * (Upgrade path: swap for Tiptap without changing the form contract — the field still submits HTML.)
 */
export function HtmlEditor({ name, defaultValue = "", rows = 18, label }: Props) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const [value, setValue] = useState(defaultValue);
  const [preview, setPreview] = useState(false);
  const [uploading, setUploading] = useState(false);

  function wrap(before: string, after = "") {
    const el = ref.current; if (!el) return;
    const { selectionStart: s, selectionEnd: e } = el;
    const selected = value.slice(s, e) || "текст";
    const next = `${value.slice(0, s)}${before}${selected}${after}${value.slice(e)}`;
    setValue(next);
    requestAnimationFrame(() => { el.focus(); el.setSelectionRange(s + before.length, s + before.length + selected.length); });
  }
  function insertBlock(html: string) {
    const el = ref.current; if (!el) return;
    const s = el.selectionStart;
    setValue(`${value.slice(0, s)}\n${html}\n${value.slice(s)}`);
  }
  async function upload(file: File) {
    setUploading(true);
    try {
      const fd = new FormData(); fd.set("file", file);
      const res = await fetch("/api/admin/upload/", { method: "POST", body: fd });
      const data = (await res.json()) as { url?: string; error?: string };
      if (data.url) insertBlock(`<p><img src="${data.url}" alt="" /></p>`); else alert(data.error ?? "Ошибка загрузки");
    } finally { setUploading(false); }
  }

  const tools = [
    { id: "bold", icon: Bold, title: "Жирный" },
    { id: "h2", icon: Heading2, title: "Заголовок H2" },
    { id: "h3", icon: Heading3, title: "Заголовок H3" },
    { id: "list", icon: List, title: "Список" },
    { id: "link", icon: Link2, title: "Ссылка" },
  ] as const;
  function runTool(id: (typeof tools)[number]["id"]) {
    if (id === "bold") wrap("<strong>", "</strong>");
    else if (id === "h2") wrap("<h2>", "</h2>");
    else if (id === "h3") wrap("<h3>", "</h3>");
    else if (id === "list") insertBlock("<ul>\n  <li>пункт</li>\n  <li>пункт</li>\n</ul>");
    else if (id === "link") { const href = prompt("Адрес ссылки (URL):", "https://"); if (href) wrap(`<a href="${href}">`, "</a>"); }
  }

  return (
    <div className="block">
      {label ? <span className="label">{label}</span> : null}
      <div className="overflow-hidden rounded-2xl border-2 border-ink-200 focus-within:border-brand-400">
        <div className="flex flex-wrap items-center gap-1 border-b border-ink-100 bg-ink-50 px-2 py-1.5">
          {tools.map((t) => <button key={t.id} type="button" title={t.title} onClick={() => runTool(t.id)} className="rounded-lg p-2 text-ink-600 hover:bg-white hover:text-brand-700"><t.icon className="h-4 w-4" /></button>)}
          <label title="Вставить изображение" className="cursor-pointer rounded-lg p-2 text-ink-600 hover:bg-white hover:text-brand-700">
            {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ImageIcon className="h-4 w-4" />}
            <input type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); e.target.value = ""; }} />
          </label>
          <span className="ml-auto flex items-center rounded-lg bg-white p-0.5 text-xs font-bold">
            <button type="button" onClick={() => setPreview(false)} className={`rounded-md px-2 py-1 ${!preview ? "bg-brand-500 text-white" : "text-ink-500"}`}><Code className="mr-1 inline h-3.5 w-3.5" />HTML</button>
            <button type="button" onClick={() => setPreview(true)} className={`rounded-md px-2 py-1 ${preview ? "bg-brand-500 text-white" : "text-ink-500"}`}><Eye className="mr-1 inline h-3.5 w-3.5" />Просмотр</button>
          </span>
        </div>
        <textarea ref={ref} name={name} value={value} onChange={(e) => setValue(e.target.value)} rows={rows} hidden={preview} className="block w-full resize-y px-4 py-3 font-mono text-[13px] leading-relaxed text-ink-800 outline-none" spellCheck={false} />
        {preview ? <div className="rich max-h-[32rem] overflow-auto px-5 py-4" dangerouslySetInnerHTML={{ __html: value }} /> : null}
      </div>
    </div>
  );
}
