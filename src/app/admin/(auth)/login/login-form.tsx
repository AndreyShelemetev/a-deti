"use client";

import { useActionState } from "react";
import { login } from "@/server/actions/auth";
import { Field, FormMessage, SubmitButton, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

export function LoginForm({ next }: { next: string }) {
  const [state, action] = useActionState(login, { ok: false } as FormState);
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="next" value={next} />
      <Field label="E-mail" error={state.errors?.email}><TextInput name="email" type="email" required autoComplete="username" /></Field>
      <Field label="Пароль" error={state.errors?.password}><TextInput name="password" type="password" required autoComplete="current-password" /></Field>
      <FormMessage state={state} />
      <SubmitButton className="w-full" pendingText="Входим…">Войти</SubmitButton>
    </form>
  );
}
