"use client";

import { useActionState } from "react";
import Link from "next/link";
import { submitPayment } from "@/server/actions/payments";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function PaymentForm({ cashback = false }: { cashback?: boolean }) {
  const [state, action] = useActionState(submitPayment, initial);
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Номер договора *" error={state.errors?.orderNumber}>
          <TextInput name="orderNumber" required placeholder="Например, 1234/26" />
        </Field>
        <Field label="Сумма, ₽ *" error={state.errors?.amount}>
          <TextInput name="amount" type="number" min={1} step={1} required inputMode="numeric" placeholder="72900" />
        </Field>
        <Field label="Фамилия Имя Отчество *" error={state.errors?.fullName} className="sm:col-span-2">
          <TextInput name="fullName" required autoComplete="name" />
        </Field>
        <Field label="Телефон" error={state.errors?.phone}>
          <TextInput name="phone" type="tel" autoComplete="tel" placeholder="+7 (___) ___-__-__" />
        </Field>
        <Field label="Электронная почта *" error={state.errors?.email} hint="На неё придёт чек">
          <TextInput name="email" type="email" required autoComplete="email" />
        </Field>
      </div>
      {cashback ? (
        <label className="flex cursor-pointer items-start gap-3 text-sm text-ink-600">
          <input type="checkbox" name="isMirCard" className="mt-0.5 h-5 w-5 rounded-md border-2 border-ink-300 accent-brand-500" />
          <span>
            Я произвожу оплату картой, зарегистрированной в{" "}
            <Link href="https://privetmir.ru/" target="_blank" rel="noopener" className="font-bold text-brand-600 underline underline-offset-2">программе лояльности МИР</Link>
          </span>
        </label>
      ) : null}
      <Consent error={state.errors?.consent} />
      <p className="text-xs text-ink-400">* Поля, отмеченные звёздочкой, обязательны для заполнения.</p>
      <FormMessage state={state} />
      <SubmitButton className="btn-accent" pendingText="Переходим к оплате…">Оплатить</SubmitButton>
    </form>
  );
}
