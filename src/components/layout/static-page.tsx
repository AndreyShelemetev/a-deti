import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { buildMetadata } from "@/lib/seo";
import { getPage } from "@/server/queries/pages";
import { getSettings } from "@/server/queries/settings";
import { PageShell } from "@/components/layout/page-shell";
import { HelpCard } from "@/components/layout/help-card";
import { RichText } from "@/components/ui/rich-text";
import type { ReactNode } from "react";

export async function staticPageMetadata(slug: string, path = `/${slug}/`): Promise<Metadata> {
  const page = await getPage(slug);
  if (!page) return {};
  return buildMetadata({ title: page.seoTitle || page.title, description: page.metaDescription, keywords: page.metaKeywords, path });
}

/** Generic CMS page: title + rich HTML from the admin panel, with an optional extra block below. */
export async function StaticPage({ slug, children, before, wide = false, aside = true }: { slug: string; children?: ReactNode; before?: ReactNode; wide?: boolean; aside?: boolean }) {
  const [page, settings] = await Promise.all([getPage(slug), getSettings()]);
  if (!page) notFound();
  return (
    <PageShell title={page.title} crumbs={[{ name: page.title }]} aside={aside ? <HelpCard settings={settings} /> : undefined} wide={wide}>
      {before}
      {page.html ? <article className="card p-6 sm:p-8"><RichText html={page.html} /></article> : null}
      {children}
    </PageShell>
  );
}
