import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

type Props = { accent?: ReactNode; children: ReactNode; as?: "h1" | "h2" | "h3"; className?: string; subtitle?: ReactNode };

export function SectionTitle({ accent, children, as: Tag = "h2", className, subtitle }: Props) {
  return (
    <div className={cn("mb-6 sm:mb-8", className)}>
      <Tag className="section-title">
        {accent ? <span className="accent">{accent} </span> : null}
        {children}
      </Tag>
      {subtitle ? <p className="mt-2 max-w-2xl text-base text-ink-500 sm:text-lg">{subtitle}</p> : null}
    </div>
  );
}
