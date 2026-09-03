"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Select } from "@/components/ui/form-fields";

const OPTIONS = [
  ["default", "По популярности"],
  ["price_asc", "Сначала дешевле"],
  ["price_desc", "Сначала дороже"],
  ["new", "Сначала новые"],
] as const;

export function SortSelect() {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();
  const value = sp.get("sort") ?? "default";
  return (
    <label className="flex items-center gap-2 text-sm font-semibold text-ink-600">
      <span className="hidden sm:inline">Сортировка:</span>
      <Select
        value={value}
        aria-label="Сортировка"
        className="!w-auto !py-2 !text-sm"
        onChange={(e) => {
          const next = new URLSearchParams(sp.toString());
          if (e.target.value === "default") next.delete("sort"); else next.set("sort", e.target.value);
          next.delete("page");
          router.push(`${pathname}${next.toString() ? `?${next}` : ""}`);
        }}
      >
        {OPTIONS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
      </Select>
    </label>
  );
}
