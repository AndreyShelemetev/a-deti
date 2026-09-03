import type { ReactNode } from "react";
import { Breadcrumbs, type Crumb } from "@/components/ui/breadcrumbs";
import { cn } from "@/lib/utils";

type Props = { title: ReactNode; crumbs: Crumb[]; children: ReactNode; aside?: ReactNode; lead?: ReactNode; className?: string; wide?: boolean };

export function PageShell({ title, crumbs, children, aside, lead, className, wide = false }: Props) {
  return (
    <div className={cn("container-site py-6 sm:py-8", className)}>
      <Breadcrumbs items={crumbs} />
      <h1 className="text-3xl font-black text-ink-900 sm:text-4xl lg:text-5xl">{title}</h1>
      {lead ? <p className="mt-3 max-w-3xl text-lg text-ink-600">{lead}</p> : null}
      <div className={cn("mt-8", aside ? "grid gap-8 lg:grid-cols-12" : "")}>
        <div className={cn(aside ? "lg:col-span-8" : "", !aside && !wide ? "max-w-4xl" : "")}>{children}</div>
        {aside ? <aside className="lg:col-span-4">{aside}</aside> : null}
      </div>
    </div>
  );
}
