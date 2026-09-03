"use client";

import { useEffect, useRef, type ReactNode } from "react";
import { X } from "lucide-react";

type Props = { open: boolean; onClose: () => void; title?: ReactNode; children: ReactNode; size?: "md" | "lg" };

export function Modal({ open, onClose, title, children, size = "md" }: Props) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (open && !el.open) el.showModal();
    if (!open && el.open) el.close();
  }, [open]);

  useEffect(() => {
    if (!open) return;
    document.body.style.overflow = "hidden";
    return () => { document.body.style.overflow = ""; };
  }, [open]);

  return (
    <dialog
      ref={ref}
      onClose={onClose}
      onClick={(e) => { if (e.target === ref.current) onClose(); }}
      className={`m-auto w-[calc(100%-1.5rem)] ${size === "lg" ? "max-w-3xl" : "max-w-lg"} rounded-3xl bg-white p-0 shadow-soft backdrop:bg-ink-900/50 backdrop:backdrop-blur-sm open:animate-pop`}
    >
      <div className="relative p-6 sm:p-8">
        <button type="button" onClick={onClose} aria-label="Закрыть" className="absolute right-4 top-4 rounded-full p-2 text-ink-400 hover:bg-ink-100 hover:text-ink-800">
          <X className="h-5 w-5" />
        </button>
        {title ? <h3 className="mb-4 pr-10 text-2xl font-extrabold text-ink-900">{title}</h3> : null}
        {open ? children : null}
      </div>
    </dialog>
  );
}
