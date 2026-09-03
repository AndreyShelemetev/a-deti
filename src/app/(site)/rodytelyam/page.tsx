import type { Metadata } from "next";
import Link from "next/link";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";
import { getLatestPosts } from "@/server/queries/posts";
import { PostCard } from "@/components/home/news-preview";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("rodytelyam");
}

export default async function ParentsPage() {
  const articles = await getLatestPosts("ARTICLE", 3);
  return (
    <StaticPage
      slug="rodytelyam"
      before={
        <div className="mb-6 grid gap-3 sm:grid-cols-3">
          {[["📋", "Документы в лагерь", "Списки документов есть на странице каждого тура во вкладке «Документы»."], ["🚆", "Доставка", "Организуем ж/д и авиа трансфер с сопровождением.", "/transfer/"], ["💳", "Оплата онлайн", "Оплатите путёвку картой по номеру договора.", "/pay/"]].map(([icon, title, text, href]) => (
            <div key={title} className="card p-5">
              <div className="text-3xl">{icon}</div>
              <div className="mt-2 font-extrabold text-ink-900">{title}</div>
              <p className="mt-1 text-sm text-ink-600">{text}</p>
              {href ? <Link href={href} className="mt-2 inline-block text-sm font-extrabold text-brand-600 hover:underline">Подробнее →</Link> : null}
            </div>
          ))}
        </div>
      }
    >
      {articles.length ? (
        <section className="mt-10">
          <div className="mb-5 flex items-end justify-between"><h2 className="section-title !text-2xl">Полезные статьи</h2><Link href="/stati/" className="btn-ghost">Все статьи</Link></div>
          <div className="grid gap-5 sm:grid-cols-3">{articles.map((p) => <PostCard key={p.id} post={p} />)}</div>
        </section>
      ) : null}
    </StaticPage>
  );
}
