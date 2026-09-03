import type { Metadata } from "next";
import { siteConfig } from "@/config/site";
import { absoluteUrl, truncate, stripHtml } from "@/lib/utils";

type MetaInput = {
  title: string;
  description?: string | null;
  path: string;
  image?: string | null;
  noindex?: boolean;
  keywords?: string | null;
  type?: "website" | "article";
  publishedTime?: Date | string | null;
};

export function buildMetadata(input: MetaInput): Metadata {
  const description = truncate(stripHtml(input.description ?? siteConfig.defaultDescription), 300);
  const canonical = absoluteUrl(input.path);
  const image = input.image ? absoluteUrl(input.image) : absoluteUrl("/images/og-default.png");
  return {
    title: input.title,
    description,
    keywords: input.keywords ?? undefined,
    alternates: { canonical },
    robots: input.noindex ? { index: false, follow: true } : { index: true, follow: true },
    openGraph: {
      title: input.title,
      description,
      url: canonical,
      siteName: siteConfig.name,
      locale: siteConfig.locale,
      type: input.type ?? "website",
      images: [{ url: image }],
      ...(input.publishedTime ? { publishedTime: new Date(input.publishedTime).toISOString() } : {}),
    },
    twitter: { card: "summary_large_image", title: input.title, description, images: [image] },
  };
}

export function jsonLdScript(data: Record<string, unknown> | Record<string, unknown>[]) {
  return JSON.stringify(data).replace(/</g, "\\u003c");
}

export function organizationJsonLd(settings: Record<string, string>) {
  return {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    name: settings["site.name"] ?? siteConfig.name,
    legalName: settings["site.legalName"],
    url: absoluteUrl("/"),
    logo: absoluteUrl("/images/logo.svg"),
    telephone: [settings["contacts.phone1"], settings["contacts.phone2"]].filter(Boolean),
    email: settings["contacts.email"],
    address: {
      "@type": "PostalAddress",
      streetAddress: "Цветной бульвар д. 26 с1, помещение 23",
      addressLocality: "Москва",
      postalCode: "127051",
      addressCountry: "RU",
    },
    geo: settings["contacts.mapLat"] ? { "@type": "GeoCoordinates", latitude: settings["contacts.mapLat"], longitude: settings["contacts.mapLng"] } : undefined,
    openingHours: "Mo-Fr 10:00-18:00",
    sameAs: [settings["social.vk"], settings["social.telegram"]].filter(Boolean),
  };
}

export function breadcrumbsJsonLd(items: { name: string; href?: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: it.name,
      ...(it.href ? { item: absoluteUrl(it.href) } : {}),
    })),
  };
}

export function tourJsonLd(tour: {
  title: string; slug: string; short: string; cover: string | null; priceMin: number | null; address: string;
  shifts: { name: string; price: number | null; startDate: Date | null; endDate: Date | null }[];
  images: { url: string }[];
}) {
  const offers = tour.shifts.filter((s) => s.price).map((s) => ({
    "@type": "Offer",
    name: s.name,
    price: s.price,
    priceCurrency: "RUB",
    availability: "https://schema.org/InStock",
    url: absoluteUrl(`/tour/${tour.slug}/`),
    ...(s.startDate ? { validFrom: s.startDate.toISOString() } : {}),
  }));
  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: tour.title,
    description: tour.short,
    image: [tour.cover, ...tour.images.map((i) => i.url)].filter(Boolean).slice(0, 6).map((u) => absoluteUrl(u as string)),
    brand: { "@type": "Organization", name: siteConfig.name },
    ...(offers.length
      ? { offers: offers.length === 1 ? offers[0] : { "@type": "AggregateOffer", lowPrice: tour.priceMin, priceCurrency: "RUB", offerCount: offers.length, offers } }
      : {}),
  };
}

export function articleJsonLd(post: { title: string; excerpt: string | null; image: string | null; publishedAt: Date; updatedAt: Date; slug: string; kind: string }) {
  const path = post.kind === "NEWS" ? `/novosti/${post.slug}/` : `/stati/${post.slug}/`;
  return {
    "@context": "https://schema.org",
    "@type": post.kind === "NEWS" ? "NewsArticle" : "Article",
    headline: post.title,
    description: post.excerpt ?? undefined,
    image: post.image ? [absoluteUrl(post.image)] : undefined,
    datePublished: post.publishedAt.toISOString(),
    dateModified: post.updatedAt.toISOString(),
    mainEntityOfPage: absoluteUrl(path),
    author: { "@type": "Organization", name: siteConfig.name },
    publisher: { "@type": "Organization", name: siteConfig.name, logo: { "@type": "ImageObject", url: absoluteUrl("/images/logo.svg") } },
  };
}
