"use client";

import { useId, useState, type ReactNode } from "react";
import { cn } from "@/lib/utils";

export type TabItem = { key: string; title: string; content: ReactNode };

export function Tabs({ items, className }: { items: TabItem[]; className?: string }) {
  const [active, setActive] = useState(items[0]?.key);
  const id = useId();
  if (!items.length) return null;
  return (
    <div className={className}>
      <div role="tablist" aria-label="Разделы" className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
        {items.map((t) => (
          <button
            key={t.key}
            role="tab"
            id={`${id}-tab-${t.key}`}
            aria-selected={active === t.key}
            aria-controls={`${id}-panel-${t.key}`}
            onClick={() => setActive(t.key)}
            className={cn(
              "whitespace-nowrap rounded-full px-4 py-2.5 text-sm font-extrabold transition-colors",
              active === t.key ? "bg-brand-500 text-white shadow-pop" : "bg-white text-ink-700 shadow-card hover:bg-brand-50",
            )}
          >
            {t.title}
          </button>
        ))}
      </div>
      {items.map((t) => (
        <div key={t.key} role="tabpanel" id={`${id}-panel-${t.key}`} aria-labelledby={`${id}-tab-${t.key}`} hidden={active !== t.key} className="card mt-4 p-5 sm:p-8">
          {t.content}
        </div>
      ))}
    </div>
  );
}
