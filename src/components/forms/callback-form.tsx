"use client";

import { useActionState } from "react";
import { submitCallback } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextArea, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function CallbackForm({ pageUrl }: { pageUrl?: string }) {
  const [state, action] = useActionState(submitCallback, initial);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <input type="hidden" name="pageUrl" value={pageUrl ?? ""} />
      <Field label="Ваше имя" error={state.errors?.name}>
        <TextInput name="name" required autoComplete="name" placeholder="Как к вам обращаться" />
      </Field>
      <Field label="Телефон" error={state.errors?.phone}>
        <TextInput name="phone" type="tel" required autoComplete="tel" placeholder="+7 (___) ___-__-__" inputMode="tel" />
      </Field>
      <Field label="Ваш вопрос" error={state.errors?.message}>
        <TextArea name="message" placeholder="Кратко опишите, чем помочь" className="min-h-24" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton className="btn-accent">Заказать звонок</SubmitButton>
    </form>
  );
}
