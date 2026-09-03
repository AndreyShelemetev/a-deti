"use client";

import { useActionState } from "react";
import { submitRequest } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextArea, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function RequestForm({ pageUrl, compact = false }: { pageUrl?: string; compact?: boolean }) {
  const [state, action] = useActionState(submitRequest, initial);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <input type="hidden" name="pageUrl" value={pageUrl ?? ""} />
      <div className={compact ? "space-y-4" : "grid gap-4 sm:grid-cols-2"}>
        <Field label="Ваше имя" error={state.errors?.name}>
          <TextInput name="name" required autoComplete="name" placeholder="Как к вам обращаться" />
        </Field>
        <Field label="Телефон" error={state.errors?.phone}>
          <TextInput name="phone" type="tel" required autoComplete="tel" placeholder="+7 (___) ___-__-__" inputMode="tel" />
        </Field>
        <Field label="E-mail" error={state.errors?.email} className={compact ? "" : "sm:col-span-2"}>
          <TextInput name="email" type="email" autoComplete="email" placeholder="name@example.ru" />
        </Field>
      </div>
      <Field label="Сообщение" error={state.errors?.message}>
        <TextArea name="message" placeholder="Какой лагерь интересует, возраст ребёнка, желаемые даты…" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton>Отправить заявку</SubmitButton>
    </form>
  );
}
