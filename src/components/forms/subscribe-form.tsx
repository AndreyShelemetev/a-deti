"use client";

import { useActionState } from "react";
import { submitSubscribe } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function SubscribeForm() {
  const [state, action] = useActionState(submitSubscribe, initial);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <Field label="Ваше имя" error={state.errors?.name}>
        <TextInput name="name" autoComplete="name" />
      </Field>
      <Field label="E-mail" error={state.errors?.email}>
        <TextInput name="email" type="email" required autoComplete="email" placeholder="name@example.ru" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton className="btn-sun">Подписаться</SubmitButton>
    </form>
  );
}
