import Link from "next/link";
import { ArrowRight, CalendarDays } from "lucide-react";
import { SafeImage } from "@/components/ui/safe-image";
import { SectionTitle } from "@/components/ui/section-title";
import { SubscribeButton } from "@/components/forms/lead-modals";
import { formatDate } from "@/lib/utils";

type PostItem = { id: number; slug: string; title: string; excerpt: string | null; image: string | null; publishedAt: Date; kind: "NEWS" | "ARTICLE" };

export function PostCard({ post }: { post: PostItem }) {
  const href = post.kind === "NEWS" ? `/novosti/${post.slug}/` : `/stati/${post.slug}/`;
  return (
    <article className="card group relative flex h-full flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-soft">
      <Link href={href} tabIndex={-1} aria-hidden className="relative block aspect-[16/10] overflow-hidden bg-brand-50">
        <SafeImage src={post.image} alt="" fill sizes="(max-width: 640px) 100vw, 33vw" className="object-cover transition-transform duration-500 group-hover:scale-105" />
      </Link>
      <div className="flex flex-1 flex-col p-5">
        <time dateTime={post.publishedAt.toISOString()} className="inline-flex items-center gap-1 text-xs font-bold text-ink-400"><CalendarDays className="h-3.5 w-3.5" />{formatDate(post.publishedAt)}</time>
        <h3 className="mt-2 text-lg font-extrabold leading-snug text-ink-900"><Link href={href} className="after:absolute after:inset-0 after:content-[''] hover:text-brand-700">{post.title}</Link></h3>
        {post.excerpt ? <p className="mt-2 line-clamp-3 text-sm text-ink-600">{post.excerpt}</p> : null}
        <span className="mt-auto pt-4 text-sm font-extrabold text-brand-600">Читать →</span>
      </div>
    </article>
  );
}

export function NewsPreview({ posts }: { posts: PostItem[] }) {
  if (!posts.length) return null;
  return (
    <section className="bg-sand py-12">
      <div className="container-site">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <SectionTitle accent="Наши" className="mb-0">новости</SectionTitle>
          <div className="flex gap-2">
            <SubscribeButton className="btn-sun">Подписаться</SubscribeButton>
            <Link href="/novosti/" className="btn-ghost">Все новости <ArrowRight className="h-4 w-4" /></Link>
          </div>
        </div>
        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{posts.map((p) => <PostCard key={p.id} post={p} />)}</div>
      </div>
    </section>
  );
}
