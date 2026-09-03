"use client";

import { useActionState } from "react";
import { submitBooking } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextArea, TextInput } from "@/components/ui/form-fields";
import { formatPrice } from "@/lib/utils";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

type Props = { tourId: number; tourTitle: string; shiftName?: string | null; amount?: number | null };

export function BookingForm({ tourId, tourTitle, shiftName, amount }: Props) {
  const [state, action] = useActionState(submitBooking, initial);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <input type="hidden" name="tourId" value={tourId} />
      <input type="hidden" name="shiftName" value={shiftName ?? ""} />
      <input type="hidden" name="amount" value={amount ?? ""} />
      <div className="rounded-2xl bg-brand-50 px-4 py-3 text-sm">
        <div className="font-extrabold text-ink-900">{tourTitle}</div>
        {shiftName ? <div className="text-ink-600">{shiftName}</div> : null}
        {amount ? <div className="mt-1 text-lg font-extrabold text-brand-700">{formatPrice(amount)}</div> : null}
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Ваше имя" error={state.errors?.name}>
          <TextInput name="name" required autoComplete="name" />
        </Field>
        <Field label="Телефон" error={state.errors?.phone}>
          <TextInput name="phone" type="tel" required autoComplete="tel" placeholder="+7 (___) ___-__-__" inputMode="tel" />
        </Field>
        <Field label="E-mail" error={state.errors?.email} className="sm:col-span-2">
          <TextInput name="email" type="email" autoComplete="email" />
        </Field>
      </div>
      <Field label="Комментарий" error={state.errors?.message}>
        <TextArea name="message" placeholder="Возраст ребёнка, количество путёвок, пожелания" className="min-h-24" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton>Отправить заявку на бронирование</SubmitButton>
    </form>
  );
}
