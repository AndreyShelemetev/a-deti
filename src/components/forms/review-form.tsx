"use client";

import { useActionState, useState } from "react";
import { Star } from "lucide-react";
import { submitReview } from "@/server/actions/leads";
import { Consent, Field, FormMessage, Honeypot, SubmitButton, SuccessCard, TextArea, TextInput } from "@/components/ui/form-fields";
import type { FormState } from "@/lib/validation";

const initial: FormState = { ok: false };

export function ReviewForm({ tourId }: { tourId?: number }) {
  const [state, action] = useActionState(submitReview, initial);
  const [rating, setRating] = useState(5);
  if (state.ok && state.message) return <SuccessCard message={state.message} />;
  return (
    <form action={action} className="relative space-y-4" noValidate>
      <Honeypot />
      {tourId ? <input type="hidden" name="tourId" value={tourId} /> : null}
      <input type="hidden" name="rating" value={rating} />
      <Field label="Оценка">
        <div className="flex gap-1" role="radiogroup" aria-label="Оценка">
          {[1, 2, 3, 4, 5].map((n) => (
            <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n} из 5`} onClick={() => setRating(n)} className="rounded-md p-0.5">
              <Star className={`h-8 w-8 ${n <= rating ? "fill-sun-400 text-sun-400" : "text-ink-200"}`} />
            </button>
          ))}
        </div>
      </Field>
      <Field label="Ваше имя" error={state.errors?.name}>
        <TextInput name="name" required autoComplete="name" />
      </Field>
      <Field label="Отзыв" error={state.errors?.text}>
        <TextArea name="text" required placeholder="Расскажите, как прошёл отдых" />
      </Field>
      <Consent error={state.errors?.consent} />
      <FormMessage state={state} />
      <SubmitButton>Отправить отзыв</SubmitButton>
    </form>
  );
}
