import type { Metadata, Viewport } from "next";
import "./globals.css";
import { siteConfig } from "@/config/site";
import { Metrika } from "@/components/layout/metrika";

export const metadata: Metadata = {
  metadataBase: new URL(siteConfig.url),
  title: { default: siteConfig.defaultTitle, template: `%s — ${siteConfig.name}` },
  description: siteConfig.defaultDescription,
  applicationName: siteConfig.name,
  openGraph: { siteName: siteConfig.name, locale: siteConfig.locale, type: "website" },
};

export const viewport: Viewport = {
  themeColor: "#3559ff",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ru" className="h-full antialiased" data-scroll-behavior="smooth">
      <body className="flex min-h-full flex-col">
        {children}
        <Metrika id={process.env.NEXT_PUBLIC_YANDEX_METRIKA_ID} />
      </body>
    </html>
  );
}
