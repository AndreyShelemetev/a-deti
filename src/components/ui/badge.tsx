import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

const tones = {
  sun: "bg-sun-100 text-sun-600",
  brand: "bg-brand-50 text-brand-700",
  mint: "bg-mint-100 text-mint-700",
  coral: "bg-coral-100 text-coral-700",
  berry: "bg-berry-100 text-berry-600",
  ink: "bg-ink-100 text-ink-700",
  white: "bg-white/90 text-ink-800 backdrop-blur",
} as const;

export type BadgeTone = keyof typeof tones;

export function Badge({ tone = "brand", className, children }: { tone?: BadgeTone; className?: string; children: ReactNode }) {
  return <span className={cn("chip", tones[tone], className)}>{children}</span>;
}

export const SEASON_TONE: Record<string, BadgeTone> = { SPRING: "mint", SUMMER: "sun", AUTUMN: "coral", WINTER: "brand" };
