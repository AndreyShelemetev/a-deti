"use client";

import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { setPaymentStatus } from "@/server/actions/admin/misc";
import { PAYMENT_STATUS_LABELS } from "@/components/admin/ui";

type Status = "NEW" | "REDIRECTED" | "PAID" | "FAILED" | "CANCELLED";

export function PaymentStatusSelect({ id, status }: { id: number; status: Status }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <select value={status} disabled={pending} onChange={(e) => start(async () => { await setPaymentStatus(id, e.target.value as Status); router.refresh(); })} className="input !w-auto !py-1 !text-xs">
      {Object.entries(PAYMENT_STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
    </select>
  );
}
