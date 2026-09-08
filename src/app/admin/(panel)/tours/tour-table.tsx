"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { Archive, Copy, Eye, EyeOff, Loader2, Star, Flame, Trash2, CalendarPlus } from "lucide-react";
import { bulkTours, duplicateTour, type BulkAction } from "@/server/actions/admin/tours";
import type { AdminTourRow } from "@/server/queries/admin-tours";
import { TOUR_TYPE_LABELS } from "@/config/site";
import { formatPrice } from "@/lib/utils";
import { formatShiftRange } from "@/lib/shifts";
import { EmptyState } from "@/components/admin/ui";

function nextShift(t: AdminTourRow) {
  const now = Date.now();
  return t.shifts.find((s) => s.startDate && s.startDate.getTime() >= now) ?? null;
}

export function TourTable({ items, status }: { items: AdminTourRow[]; status: string }) {
  const [selected, setSelected] = useState<number[]>([]);
  const [pending, start] = useTransition();
  const router = useRouter();
  const all = items.length > 0 && selected.length === items.length;
  const run = (action: BulkAction, ids = selected) => {
    if (action === "delete" && !window.confirm(`Удалить ${ids.length} тур(ов) без возможности восстановления?`)) return;
    if (action === "archive" && !window.confirm(`Отправить ${ids.length} тур(ов) в архив? Они пропадут с сайта, но останутся здесь.`)) return;
    start(async () => { await bulkTours(ids, action); setSelected([]); router.refresh(); });
  };
  const copy = (id: number, nextSeason: boolean) => start(async () => { await duplicateTour(id, nextSeason); });

  if (!items.length) return <EmptyState text="Туров с такими параметрами нет. Измените фильтры или добавьте тур." />;

  return (
    <div className="space-y-3">
      {selected.length ? (
        <div className="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-2xl bg-ink-900 px-4 py-2.5 text-sm text-white shadow-soft">
          <span className="font-bold">Выбрано: {selected.length}</span>
          {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
          <span className="mx-1 opacity-40">|</span>
          <button onClick={() => run("publish")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><Eye className="mr-1 inline h-3.5 w-3.5" />Опубликовать</button>
          <button onClick={() => run("unpublish")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><EyeOff className="mr-1 inline h-3.5 w-3.5" />Скрыть</button>
          {status === "archived" ? (
            <button onClick={() => run("unarchive")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><Archive className="mr-1 inline h-3.5 w-3.5" />Вернуть из архива</button>
          ) : (
            <button onClick={() => run("archive")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><Archive className="mr-1 inline h-3.5 w-3.5" />В архив</button>
          )}
          <button onClick={() => run("best_on")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><Star className="mr-1 inline h-3.5 w-3.5" />★ Лучшее</button>
          <button onClick={() => run("best_off")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20">Снять ★</button>
          <button onClick={() => run("hit_on")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20"><Flame className="mr-1 inline h-3.5 w-3.5" />Хит</button>
          <button onClick={() => run("hit_off")} className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold hover:bg-white/20">Снять хит</button>
          <button onClick={() => run("delete")} className="ml-auto rounded-full bg-coral-500/80 px-3 py-1 text-xs font-bold hover:bg-coral-500"><Trash2 className="mr-1 inline h-3.5 w-3.5" />Удалить</button>
        </div>
      ) : null}
      <div className="overflow-x-auto rounded-2xl bg-white shadow-card">
        <table className="w-full min-w-[900px] text-sm">
          <thead>
            <tr className="bg-ink-50 text-left text-xs font-extrabold uppercase tracking-wide text-ink-500">
              <th className="w-10 px-3 py-3"><input type="checkbox" checked={all} onChange={(e) => setSelected(e.target.checked ? items.map((t) => t.id) : [])} aria-label="Выбрать все" className="h-4 w-4 accent-brand-500" /></th>
              <th className="px-3 py-3">Тур</th><th className="px-3 py-3">Направление</th><th className="px-3 py-3">Ближайшая смена</th><th className="px-3 py-3">Цена от</th><th className="px-3 py-3">Наполнение</th><th className="px-3 py-3">Статус</th><th className="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {items.map((t) => {
              const ns = nextShift(t);
              const soldOut = t.shifts.length > 0 && t.shifts.every((s) => s.availability === "SOLD_OUT");
              const gaps = [!t.cover && "нет обложки", !t.shifts.length && "нет смен", t.priceMin == null && "нет цены", !t._count.sections && "нет описания"].filter(Boolean) as string[];
              return (
                <tr key={t.id} className={`border-t border-ink-100 hover:bg-brand-50/40 ${selected.includes(t.id) ? "bg-brand-50/60" : ""}`}>
                  <td className="px-3 py-2.5 align-top"><input type="checkbox" checked={selected.includes(t.id)} onChange={(e) => setSelected(e.target.checked ? [...selected, t.id] : selected.filter((x) => x !== t.id))} aria-label="Выбрать" className="mt-1 h-4 w-4 accent-brand-500" /></td>
                  <td className="px-3 py-2.5">
                    <div className="flex gap-3">
                      <div className="relative h-14 w-20 shrink-0 overflow-hidden rounded-lg bg-ink-100">{t.cover ? <Image src={t.cover} alt="" fill sizes="80px" className="object-cover" /> : null}</div>
                      <div className="min-w-0">
                        <Link href={`/admin/tours/${t.id}/`} className="line-clamp-2 font-bold text-brand-600 hover:underline">{t.title}</Link>
                        <div className="mt-0.5 flex flex-wrap gap-1 text-[11px] text-ink-400">
                          <span>{TOUR_TYPE_LABELS[t.type]}</span>{t.ageText ? <span>· {t.ageText}</span> : null}{t.seasonText ? <span>· {t.seasonText}</span> : null}
                          {t.isBest ? <span className="text-sun-600">· ★ лучшее</span> : null}{t.isHit ? <span className="text-coral-600">· 🔥 хит</span> : null}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-3 py-2.5 align-top text-ink-700">{t.region?.name ?? "—"}</td>
                  <td className="px-3 py-2.5 align-top">
                    {ns ? <><div className="font-bold text-ink-900">{formatShiftRange(ns.startDate, ns.endDate)}</div><div className="text-xs text-ink-400">{t.shifts.length} смен</div></> : t.shifts.length ? <span className="text-xs text-coral-600">все {t.shifts.length} смен прошли</span> : <span className="text-xs text-ink-400">—</span>}
                  </td>
                  <td className="px-3 py-2.5 align-top font-bold text-ink-900">{t.priceMin ? formatPrice(t.priceMin) : t.priceText ?? "—"}</td>
                  <td className="px-3 py-2.5 align-top text-xs">
                    <div className="text-ink-500">{t._count.images} фото · {t._count.sections} вкл.</div>
                    {gaps.length ? <div className="text-coral-600">{gaps.join(", ")}</div> : <div className="text-mint-700">заполнено</div>}
                    {soldOut ? <div className="font-bold text-coral-600">мест нет</div> : null}
                  </td>
                  <td className="px-3 py-2.5 align-top">
                    {t.archivedAt ? <span className="chip bg-ink-100 text-ink-500">Архив</span> : t.isPublished ? <span className="chip bg-mint-100 text-mint-700">На сайте</span> : <span className="chip bg-sun-100 text-sun-600">Черновик</span>}
                  </td>
                  <td className="px-3 py-2.5 align-top">
                    <div className="flex flex-col gap-1 text-xs">
                      <button onClick={() => copy(t.id, false)} disabled={pending} className="inline-flex items-center gap-1 text-ink-500 hover:text-brand-700" title="Создать копию"><Copy className="h-3.5 w-3.5" /> копия</button>
                      <button onClick={() => copy(t.id, true)} disabled={pending} className="inline-flex items-center gap-1 text-ink-500 hover:text-brand-700" title="Копия на следующий сезон: даты смен и годы в названиях +1"><CalendarPlus className="h-3.5 w-3.5" /> след. сезон</button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
