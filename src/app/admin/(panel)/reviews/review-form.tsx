"use client";

import { AdminForm, Checkbox, F, inputCls, selectCls } from "@/components/admin/form";
import { saveReview } from "@/server/actions/admin/misc";

export type ReviewFormData = { id?: number; author: string; text: string; rating: number; source: string; sourceUrl: string | null; publishedAt: string; isPublished: boolean; tourId: number | null };

export function ReviewForm({ review, tours }: { review: ReviewFormData; tours: { id: number; title: string }[] }) {
  return (
    <AdminForm action={saveReview}>
      {review.id ? <input type="hidden" name="id" value={review.id} /> : null}
      <div className="grid gap-4 sm:grid-cols-2">
        <F label="Автор" name="author"><input name="author" defaultValue={review.author} required className={inputCls} /></F>
        <F label="Оценка" name="rating"><select name="rating" defaultValue={review.rating} className={selectCls}>{[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{n}</option>)}</select></F>
        <F label="Текст" name="text" className="sm:col-span-2"><textarea name="text" defaultValue={review.text} rows={5} required className={inputCls} /></F>
        <F label="Источник" name="source"><select name="source" defaultValue={review.source} className={selectCls}><option value="SITE">Сайт</option><option value="YANDEX">Яндекс</option><option value="GOOGLE">Google</option></select></F>
        <F label="Ссылка на источник" name="sourceUrl"><input name="sourceUrl" defaultValue={review.sourceUrl ?? ""} className={inputCls} /></F>
        <F label="Дата" name="publishedAt"><input name="publishedAt" type="date" defaultValue={review.publishedAt} className={inputCls} /></F>
        <F label="Тур (необязательно)" name="tourId"><select name="tourId" defaultValue={review.tourId ?? ""} className={selectCls}><option value="">—</option>{tours.map((t) => <option key={t.id} value={t.id}>{t.title}</option>)}</select></F>
        <div className="sm:col-span-2"><Checkbox name="isPublished" label="Опубликован" defaultChecked={review.isPublished} /></div>
      </div>
    </AdminForm>
  );
}
