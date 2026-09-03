"use client";

import { useFormStatus } from "react-dom";
import Link from "next/link";
import { Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";
import type { FormState } from "@/lib/validation";
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from "react";

export function Field({ label, error, children, hint, className }: { label?: ReactNode; error?: string[]; children: ReactNode; hint?: ReactNode; className?: string }) {
  return (
    <div className={className}>
      {label ? <label className="label">{label}</label> : null}
      {children}
      {hint && !error ? <p className="mt-1 text-xs text-ink-400">{hint}</p> : null}
      {error?.length ? <p className="mt-1 text-sm font-semibold text-coral-600">{error[0]}</p> : null}
    </div>
  );
}

export function TextInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={cn("input", className)} />;
}

export function TextArea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={cn("input min-h-28", className)} />;
}

export function Select({ className, children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select {...props} className={cn("input appearance-none bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2220%22 height=%2220%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%234a4f7d%22 stroke-width=%222.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><path d=%22m6 9 6 6 6-6%22/></svg>')] bg-[length:20px] bg-[right_0.9rem_center] bg-no-repeat pr-10", className)}>
      {children}
    </select>
  );
}

export function Consent({ error, name = "consent" }: { error?: string[]; name?: string }) {
  return (
    <div>
      <label className="flex cursor-pointer items-start gap-3 text-sm text-ink-600">
        <input type="checkbox" name={name} required className="mt-0.5 h-5 w-5 shrink-0 rounded-md border-2 border-ink-300 accent-brand-500" />
        <span>
          Я согласен(а) с{" "}
          <Link href="/personal-data/" target="_blank" className="font-bold text-brand-600 underline underline-offset-2">
            политикой обработки персональных данных
          </Link>
        </span>
      </label>
      {error?.length ? <p className="mt-1 text-sm font-semibold text-coral-600">{error[0]}</p> : null}
    </div>
  );
}

/** Anti-spam honeypot: hidden field that real users never fill in. */
export function Honeypot() {
  return (
    <div className="absolute -left-[9999px] top-0 h-0 w-0 overflow-hidden" aria-hidden>
      <label>Website <input type="text" name="website" tabIndex={-1} autoComplete="off" /></label>
    </div>
  );
}

export function SubmitButton({ children, className, pendingText = "Отправляем…" }: { children: ReactNode; className?: string; pendingText?: string }) {
  const { pending } = useFormStatus();
  return (
    <button type="submit" disabled={pending} className={cn("btn-primary w-full sm:w-auto", className)}>
      {pending ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> : null}
      {pending ? pendingText : children}
    </button>
  );
}

export function FormMessage({ state }: { state: FormState }) {
  if (!state.message) return null;
  return (
    <div role={state.ok ? "status" : "alert"} className={cn("rounded-2xl px-4 py-3 text-sm font-semibold", state.ok ? "bg-mint-100 text-mint-700" : "bg-coral-100 text-coral-700")}>
      {state.message}
    </div>
  );
}

export function SuccessCard({ message }: { message: string }) {
  return (
    <div role="status" className="rounded-3xl bg-mint-50 p-6 text-center">
      <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-mint-400 text-3xl text-white">✓</div>
      <p className="text-base font-bold text-mint-700">{message}</p>
    </div>
  );
}
