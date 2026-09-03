import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { LoginForm } from "./login-form";

export const metadata: Metadata = { title: "Вход в панель управления", robots: { index: false, follow: false } };

export default async function LoginPage(props: PageProps<"/admin/login">) {
  const session = await getSession();
  if (session) redirect("/admin/");
  const sp = await props.searchParams;
  const next = (Array.isArray(sp.next) ? sp.next[0] : sp.next) ?? "/admin/";
  return (
    <div className="w-full max-w-sm rounded-3xl bg-white p-8 shadow-soft">
      <div className="mb-6 text-center">
        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500 text-2xl">🔐</div>
        <h1 className="text-2xl font-black text-ink-900">Панель управления</h1>
        <p className="mt-1 text-sm text-ink-500">А-тур · вход для сотрудников</p>
      </div>
      <LoginForm next={next} />
    </div>
  );
}
