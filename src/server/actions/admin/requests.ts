"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/prisma";
import { requireSession } from "@/lib/auth";
import type { FormState } from "@/lib/validation";

const schema = z.object({ id: z.coerce.number().int(), status: z.enum(["NEW", "IN_PROGRESS", "DONE", "SPAM"]), note: z.string().max(5000).optional() });

export async function updateRequest(_prev: FormState, formData: FormData): Promise<FormState> {
  await requireSession();
  const parsed = schema.safeParse(Object.fromEntries(formData.entries()));
  if (!parsed.success) return { ok: false, message: "Проверьте поля" };
  await prisma.request.update({ where: { id: parsed.data.id }, data: { status: parsed.data.status, note: parsed.data.note || null } });
  return { ok: true, message: "Сохранено" };
}

export async function deleteRequest(id: number) {
  await requireSession();
  await prisma.request.delete({ where: { id } });
  redirect("/admin/requests/");
}
