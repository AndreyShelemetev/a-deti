import type { Metadata } from "next";
import Link from "next/link";
import Form from "next/form";
import { Search } from "lucide-react";
import { buildMetadata } from "@/lib/seo";
import { plural } from "@/lib/utils";
import { searchSite } from "@/server/queries/search";
import { PageShell } from "@/components/layout/page-shell";
import { TourList } from "@/components/tour/tour-list";
import { PostCard } from "@/components/home/news-preview";
import { TextInput } from "@/components/ui/form-fields";

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: "Поиск по сайту", description: "Поиск туров, лагерей, новостей и статей на сайте А-тур.", path: "/search/", noindex: true });
}

export default async function SearchPage(props: PageProps<"/search">) {
  const sp = await props.searchParams;
  const q = (Array.isArray(sp.q) ? sp.q[0] : sp.q) ?? "";
  const results = q ? await searchSite(q) : null;
  const total = results ? results.tours.length + results.posts.length + results.pages.length : 0;
  return (
    <PageShell title="Поиск" crumbs={[{ name: "Поиск" }]} wide>
      <Form action="/search/" className="card flex flex-col gap-3 p-4 sm:flex-row">
        <TextInput name="q" defaultValue={q} placeholder="Например: Орлёнок, Крым, языковой лагерь…" aria-label="Поисковый запрос" autoFocus />
        <button type="submit" className="btn-primary sm:w-auto"><Search className="h-4 w-4" /> Искать</button>
      </Form>
      {results ? (
        <div className="mt-8 space-y-10">
          <p className="text-sm font-bold text-ink-600">По запросу «{q}» {total ? `найдено ${total} ${plural(total, ["результат", "результата", "результатов"])}` : "ничего не найдено. Попробуйте другое слово или посмотрите весь каталог."}</p>
          {results.tours.length ? <section><h2 className="section-title mb-5 !text-2xl">Туры и лагеря</h2><TourList items={results.tours} /></section> : null}
          {results.posts.length ? <section><h2 className="section-title mb-5 !text-2xl">Новости и статьи</h2><div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{results.posts.map((p) => <PostCard key={p.id} post={p} />)}</div></section> : null}
          {results.pages.length ? <section><h2 className="section-title mb-5 !text-2xl">Страницы</h2><ul className="space-y-2">{results.pages.map((p) => <li key={p.slug}><Link href={`/${p.slug}/`} className="font-extrabold text-brand-600 hover:underline">{p.title}</Link></li>)}</ul></section> : null}
          {!total ? <Link href="/tour/" className="btn-outline">Смотреть все туры</Link> : null}
        </div>
      ) : null}
    </PageShell>
  );
}
