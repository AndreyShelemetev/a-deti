import Link from "next/link";
import { Search } from "lucide-react";

export default function NotFound() {
  return (
    <div className="container-site py-20 text-center">
      <div className="mx-auto mb-6 flex h-28 w-28 items-center justify-center rounded-full bg-sun-100 text-6xl">🧭</div>
      <h1 className="text-4xl font-black text-ink-900">Ошибка 404. Нет такой страницы</h1>
      <p className="mx-auto mt-4 max-w-lg text-ink-600">Страница, которую вы ищете, была перемещена, переименована или никогда не существовала. Перейдите на главную или воспользуйтесь поиском.</p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        <Link href="/" className="btn-primary">На главную</Link>
        <Link href="/tour/" className="btn-outline">Все туры</Link>
        <Link href="/search/" className="btn-ghost"><Search className="h-4 w-4" /> Поиск</Link>
      </div>
    </div>
  );
}
