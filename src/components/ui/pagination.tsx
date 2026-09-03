import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

type Props = { page: number; pages: number; hrefFor: (page: number) => string; className?: string };

function range(page: number, pages: number) {
  const set = new Set<number>([1, pages, page, page - 1, page + 1, page - 2, page + 2]);
  return [...set].filter((p) => p >= 1 && p <= pages).sort((a, b) => a - b);
}

export function Pagination({ page, pages, hrefFor, className }: Props) {
  if (pages <= 1) return null;
  const items = range(page, pages);
  return (
    <nav aria-label="Пагинация" className={cn("mt-10 flex flex-wrap items-center justify-center gap-2", className)}>
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} rel="prev" className="btn-outline h-11 w-11 !px-0" aria-label="Предыдущая страница">
          <ChevronLeft className="h-5 w-5" />
        </Link>
      ) : null}
      {items.map((p, i) => {
        const gap = i > 0 && p - items[i - 1] > 1;
        return (
          <span key={p} className="contents">
            {gap ? <span className="px-1 text-ink-400">…</span> : null}
            {p === page ? (
              <span aria-current="page" className="btn-primary h-11 min-w-11 !px-3">{p}</span>
            ) : (
              <Link href={hrefFor(p)} className="btn-outline h-11 min-w-11 !px-3">{p}</Link>
            )}
          </span>
        );
      })}
      {page < pages ? (
        <Link href={hrefFor(page + 1)} rel="next" className="btn-outline h-11 w-11 !px-0" aria-label="Следующая страница">
          <ChevronRight className="h-5 w-5" />
        </Link>
      ) : null}
    </nav>
  );
}
