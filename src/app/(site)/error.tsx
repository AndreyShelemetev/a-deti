"use client";

export default function ErrorPage({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <div className="container-site py-20 text-center">
      <h1 className="text-3xl font-black text-ink-900">Что-то пошло не так</h1>
      <p className="mt-3 text-ink-600">Попробуйте обновить страницу. Если ошибка повторяется — позвоните нам, мы поможем.</p>
      {error.digest ? <p className="mt-2 text-xs text-ink-400">Код: {error.digest}</p> : null}
      <button type="button" onClick={retry} className="btn-primary mt-6">Попробовать снова</button>
    </div>
  );
}
