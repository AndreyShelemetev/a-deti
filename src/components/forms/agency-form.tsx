"use client";

import { useActionState } from "react";
import { submitAgency } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextArea, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function AgencyForm() {
  const [state, action] = useActionState(submitAgency, initial);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Название агентства" error={state.errors?.company} className="sm:col-span-2">
          <TextInput name="company" required autoComplete="organization" />
        </Field>
        <Field label="Контактное лицо" error={state.errors?.name}>
          <TextInput name="name" required autoComplete="name" />
        </Field>
        <Field label="Телефон" error={state.errors?.phone}>
          <TextInput name="phone" type="tel" required autoComplete="tel" placeholder="+7 (___) ___-__-__" />
        </Field>
        <Field label="E-mail" error={state.errors?.email} className="sm:col-span-2">
          <TextInput name="email" type="email" required autoComplete="email" />
        </Field>
      </div>
      <Field label="Комментарий" error={state.errors?.message}>
        <TextArea name="message" placeholder="Какие направления интересуют" className="min-h-24" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton>Получить доступ</SubmitButton>
    </form>
  );
}
