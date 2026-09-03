import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CalendarDays } from "lucide-react";
import { articleJsonLd, buildMetadata, jsonLdScript } from "@/lib/seo";
import { formatDate } from "@/lib/utils";
import { getPost, listPosts } from "@/server/queries/posts";
import { getSettings } from "@/server/queries/settings";
import { redirectIfLegacy } from "@/server/queries/redirects";
import { PageShell } from "@/components/layout/page-shell";
import { HelpCard } from "@/components/layout/help-card";
import { PostCard } from "@/components/home/news-preview";
import { Pagination } from "@/components/ui/pagination";
import { RichText } from "@/components/ui/rich-text";
import { SafeImage } from "@/components/ui/safe-image";
import { SubscribeForm } from "@/components/forms/subscribe-form";
import type { PostKind } from "@/generated/prisma/enums";

const KIND_META = {
  NEWS: { base: "/novosti/", title: "Новости", crumb: "Новости", description: "Новости туроператора А-тур: открытие продаж, заезды групп, акции и скидки на детский отдых." },
  ARTICLE: { base: "/stati/", title: "Статьи", crumb: "Все статьи", description: "Полезные статьи для родителей и вожатых: подготовка ребёнка к лагерю, безопасность, советы по отдыху." },
} as const;

export async function postListMetadata(kind: PostKind, page: number): Promise<Metadata> {
  const m = KIND_META[kind];
  return buildMetadata({ title: page > 1 ? `${m.title} — страница ${page}` : m.title, description: m.description, path: m.base, noindex: page > 1 });
}

export async function PostListPage({ kind, page }: { kind: PostKind; page: number }) {
  const m = KIND_META[kind];
  const [result, settings] = await Promise.all([listPosts(kind, page, 9), getSettings()]);
  if (page > 1 && !result.items.length) notFound();
  return (
    <PageShell title={m.title} crumbs={[{ name: m.crumb }]} lead={m.description} aside={<div className="space-y-4"><HelpCard settings={settings} /><div className="card p-6"><div className="text-lg font-extrabold text-ink-900">Подписка на новости</div><p className="mb-4 mt-1 text-sm text-ink-600">Первыми узнавайте об открытии продаж и скидках.</p><SubscribeForm /></div></div>}>
      <div className="grid gap-5 sm:grid-cols-2">{result.items.map((p) => <PostCard key={p.id} post={p} />)}</div>
      <Pagination page={result.page} pages={result.pages} hrefFor={(p) => (p > 1 ? `${m.base}?page=${p}` : m.base)} />
    </PageShell>
  );
}

export async function postMetadata(kind: PostKind, slug: string): Promise<Metadata> {
  const post = await getPost(kind, slug);
  if (!post) return {};
  const m = KIND_META[kind];
  return buildMetadata({ title: post.seoTitle || post.title, description: post.metaDescription || post.excerpt, path: `${m.base}${post.slug}/`, image: post.image, type: "article", publishedTime: post.publishedAt });
}

export async function PostDetailPage({ kind, slug }: { kind: PostKind; slug: string }) {
  const post = await getPost(kind, slug);
  const m = KIND_META[kind];
  if (!post) {
    await redirectIfLegacy(`${m.base}${slug}/`);
    notFound();
  }
  const [settings, more] = await Promise.all([getSettings(), listPosts(kind, 1, 4)]);
  const related = more.items.filter((p) => p.id !== post.id).slice(0, 3);
  return (
    <PageShell title={post.title} crumbs={[{ name: m.crumb, href: m.base }, { name: post.title }]} aside={<HelpCard settings={settings} />}>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(articleJsonLd(post)) }} />
      <time dateTime={post.publishedAt.toISOString()} className="inline-flex items-center gap-1.5 text-sm font-bold text-ink-500"><CalendarDays className="h-4 w-4" />{formatDate(post.publishedAt)}</time>
      {post.image && !post.html.includes(post.image) ? (
        <div className="relative mt-5 aspect-[16/9] overflow-hidden rounded-3xl shadow-card"><SafeImage src={post.image} alt={post.title} fill sizes="(max-width: 1024px) 100vw, 60vw" className="object-cover" preload /></div>
      ) : null}
      <article className="card mt-6 p-6 sm:p-8"><RichText html={post.html} /></article>
      {related.length ? (
        <section className="mt-12">
          <h2 className="section-title mb-6 !text-2xl">Читайте также</h2>
          <div className="grid gap-5 sm:grid-cols-3">{related.map((p) => <PostCard key={p.id} post={p} />)}</div>
        </section>
      ) : null}
    </PageShell>
  );
}
