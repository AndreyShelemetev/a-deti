import Link from "next/link";
import { ChevronRight, Home } from "lucide-react";
import { breadcrumbsJsonLd, jsonLdScript } from "@/lib/seo";

export type Crumb = { name: string; href?: string };

export function Breadcrumbs({ items, className = "" }: { items: Crumb[]; className?: string }) {
  const all: Crumb[] = [{ name: "Главная", href: "/" }, ...items];
  return (
    <nav aria-label="Хлебные крошки" className={`mb-5 text-sm text-ink-500 ${className}`}>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbsJsonLd(all)) }} />
      <ol className="flex flex-wrap items-center gap-1.5">
        {all.map((c, i) => {
          const last = i === all.length - 1;
          return (
            <li key={i} className="flex items-center gap-1.5">
              {i > 0 ? <ChevronRight className="h-3.5 w-3.5 text-ink-300" aria-hidden /> : null}
              {c.href && !last ? (
                <Link href={c.href} className="inline-flex items-center gap-1 rounded-md font-semibold text-ink-600 hover:text-brand-600">
                  {i === 0 ? <Home className="h-3.5 w-3.5" aria-hidden /> : null}
                  {c.name}
                </Link>
              ) : (
                <span className="line-clamp-1 max-w-[60vw] font-semibold text-ink-800" aria-current="page">{c.name}</span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
