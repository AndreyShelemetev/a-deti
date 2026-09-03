import { jsonLdScript, organizationJsonLd } from "@/lib/seo";
import { getSettings } from "@/server/queries/settings";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { CookieBanner } from "@/components/layout/cookie-banner";

// Content is served from PostgreSQL and edited in the admin panel — render on demand so edits show up immediately.
export const dynamic = "force-dynamic";

export default async function SiteLayout({ children }: LayoutProps<"/">) {
  const settings = await getSettings();
  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(organizationJsonLd(settings)) }} />
      <Header settings={settings} />
      <main className="flex-1">{children}</main>
      <Footer settings={settings} />
      <CookieBanner />
    </>
  );
}
