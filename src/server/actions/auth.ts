"use server";

import { redirect } from "next/navigation";
import { clearSessionCookie, createSessionCookie, verifyCredentials } from "@/lib/auth";
import { loginSchema, zodErrors, type FormState } from "@/lib/validation";

export async function login(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = loginSchema.safeParse(Object.fromEntries(formData.entries()));
  if (!parsed.success) return { ok: false, errors: zodErrors(parsed.error) };
  const user = await verifyCredentials(parsed.data.email, parsed.data.password);
  if (!user) return { ok: false, message: "Неверный e-mail или пароль" };
  await createSessionCookie(user);
  const next = String(formData.get("next") ?? "/admin/");
  redirect(next.startsWith("/admin") ? next : "/admin/");
}

export async function logout() {
  await clearSessionCookie();
  redirect("/admin/login/");
}
