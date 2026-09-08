"use client";

import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { setShiftAvailability } from "@/server/actions/admin/tours";

type A = "AVAILABLE" | "FEW" | "SOLD_OUT";
const CLS: Record<A, string> = { AVAILABLE: "bg-mint-100 text-mint-700", FEW: "bg-sun-100 text-sun-600", SOLD_OUT: "bg-coral-100 text-coral-700" };

export function AvailabilitySelect({ id, value, seatsLeft }: { id: number; value: A; seatsLeft: number | null }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <span className="inline-flex items-center gap-2">
      <select value={value} disabled={pending} onChange={(e) => start(async () => { await setShiftAvailability(id, e.target.value as A); router.refresh(); })} className={`rounded-lg px-2 py-1 text-xs font-bold ${CLS[value]} disabled:opacity-50`}>
        <option value="AVAILABLE">Есть места</option><option value="FEW">Мало мест</option><option value="SOLD_OUT">Мест нет</option>
      </select>
      {seatsLeft != null ? <span className="text-xs text-ink-400">{seatsLeft} шт.</span> : null}
    </span>
  );
}
