"use client";

import { useActionState, useState, type ReactNode } from "react";
import { useFormStatus } from "react-dom";
import { Loader2, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

type Action = (prev: FormState, formData: FormData) => Promise<FormState>;

/** Generic admin form wrapper around a server action with inline status messages. */
export function AdminForm({ action, children, className, submitLabel = "Сохранить" }: { action: Action; children: ReactNode; className?: string; submitLabel?: string }) {
  const [state, formAction] = useActionState(action, initial);
  return (
    <form action={formAction} className={cn("space-y-5", className)}>
      <FormContext.Provider value={state}>{children}</FormContext.Provider>
      {state.message ? (
        <div role={state.ok ? "status" : "alert"} className={cn("rounded-xl px-4 py-2.5 text-sm font-semibold", state.ok ? "bg-mint-100 text-mint-700" : "bg-coral-100 text-coral-700")}>{state.message}</div>
      ) : null}
      <div className="flex items-center gap-3">
        <SaveButton>{submitLabel}</SaveButton>
      </div>
    </form>
  );
}

import { createContext, useContext } from "react";
const FormContext = createContext<FormState>(initial);
export function useFormState() { return useContext(FormContext); }

export function SaveButton({ children }: { children: ReactNode }) {
  const { pending } = useFormStatus();
  return <button type="submit" disabled={pending} className="btn-primary !py-2.5">{pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}{children}</button>;
}

export function F({ label, name, error, children, hint, className }: { label: string; name?: string; error?: string[]; children: ReactNode; hint?: string; className?: string }) {
  const state = useFormState();
  const err = error ?? (name ? state.errors?.[name] : undefined);
  return (
    <label className={cn("block", className)}>
      <span className="label">{label}</span>
      {children}
      {hint && !err ? <span className="mt-1 block text-xs text-ink-400">{hint}</span> : null}
      {err?.length ? <span className="mt-1 block text-xs font-bold text-coral-600">{err[0]}</span> : null}
    </label>
  );
}

export const inputCls = "input !py-2.5 !text-sm";
export const selectCls = "input !py-2.5 !text-sm";

export function Checkbox({ name, label, defaultChecked, value }: { name: string; label: string; defaultChecked?: boolean; value?: string | number }) {
  return (
    <label className="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-ink-700">
      <input type="checkbox" name={name} value={value} defaultChecked={defaultChecked} className="h-5 w-5 rounded-md border-2 border-ink-300 accent-brand-500" />
      {label}
    </label>
  );
}

/** Confirm-then-submit button for destructive server actions (delete). */
export function DangerAction({ action, label = "Удалить", confirm = "Удалить без возможности восстановления?" }: { action: () => Promise<void>; label?: string; confirm?: string }) {
  const [busy, setBusy] = useState(false);
  return (
    <button
      type="button"
      disabled={busy}
      onClick={async () => { if (!window.confirm(confirm)) return; setBusy(true); await action(); setBusy(false); }}
      className="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold text-coral-600 hover:bg-coral-50 disabled:opacity-50"
    >
      <Trash2 className="h-3.5 w-3.5" /> {label}
    </button>
  );
}
