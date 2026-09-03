"use client";

import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { setReviewPublished } from "@/server/actions/admin/misc";

export function PublishToggle({ id, isPublished }: { id: number; isPublished: boolean }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <button type="button" disabled={pending} onClick={() => start(async () => { await setReviewPublished(id, !isPublished); router.refresh(); })} className={`chip ${isPublished ? "bg-mint-100 text-mint-700" : "bg-sun-100 text-sun-600"} disabled:opacity-50`}>
      {isPublished ? "Опубликован" : "На модерации"} · {isPublished ? "скрыть" : "опубликовать"}
    </button>
  );
}
