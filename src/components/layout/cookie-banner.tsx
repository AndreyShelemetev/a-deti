"use client";

import { useState, useSyncExternalStore } from "react";
import Link from "next/link";

const KEY = "adeti-cookie-consent";

function subscribe(cb: () => void) {
  window.addEventListener("storage", cb);
  return () => window.removeEventListener("storage", cb);
}
function getSnapshot() {
  try { return localStorage.getItem(KEY); } catch { return "1"; }
}
function getServerSnapshot() {
  return "1"; // never render the banner on the server (avoids layout shift for consented users)
}

export function CookieBanner() {
  const stored = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  const [dismissed, setDismissed] = useState(false);
  if (stored || dismissed) return null;
  return (
    <div role="dialog" aria-label="Использование cookie" className="fixed bottom-4 left-4 right-4 z-40 mx-auto max-w-xl rounded-3xl bg-white p-4 shadow-soft ring-1 ring-ink-100 sm:bottom-6 sm:left-auto sm:right-6">
      <p className="text-sm text-ink-700">
        Сайт использует cookie для корректной работы и аналитики. Подробнее — в{" "}
        <Link href="/politiks/" className="font-bold text-brand-600 underline underline-offset-2">политике конфиденциальности</Link>.
      </p>
      <button type="button" onClick={() => { try { localStorage.setItem(KEY, "1"); } catch {} setDismissed(true); }} className="btn-primary mt-3 !py-2 !text-xs">
        Понятно
      </button>
    </div>
  );
}
