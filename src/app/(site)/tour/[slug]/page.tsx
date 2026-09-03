import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CalendarRange, ExternalLink, FileDown, MapPin, Users } from "lucide-react";
import { buildMetadata, jsonLdScript, tourJsonLd } from "@/lib/seo";
import { withYear } from "@/lib/utils";
import { getRelatedTours, getTourBySlug } from "@/server/queries/tours";
import { redirectIfLegacy } from "@/server/queries/redirects";
import { getReviewsBlock } from "@/server/queries/home";
import { getSettings } from "@/server/queries/settings";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Badge, SEASON_TONE } from "@/components/ui/badge";
import { Tabs, type TabItem } from "@/components/ui/tabs";
import { RichText } from "@/components/ui/rich-text";
import { TourGallery } from "@/components/tour/tour-gallery";
import { BookingWidget } from "@/components/tour/booking-widget";
import { TourCard } from "@/components/tour/tour-card";
import { ReviewsBlock } from "@/components/home/reviews-block";
import { SectionTitle } from "@/components/ui/section-title";
import { SEASON_LABELS, TOUR_TYPE_LABELS, TOUR_TYPE_PATHS } from "@/config/site";

export async function generateMetadata(props: PageProps<"/tour/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  const tour = await getTourBySlug(slug);
  if (!tour) return {};
  return buildMetadata({
    title: withYear(tour.seoTitle) || tour.title,
    description: tour.metaDescription || tour.short || `${tour.title}: описание программы, смены и цены, документы, отзывы. Бронирование у туроператора А-тур.`,
    keywords: tour.metaKeywords,
    path: `/tour/${tour.slug}/`,
    image: tour.cover,
  });
}

export default async function TourPage(props: PageProps<"/tour/[slug]">) {
  const { slug } = await props.params;
  const tour = await getTourBySlug(slug);
  if (!tour) {
    await redirectIfLegacy(`/tour/${slug}/`);
    notFound();
  }
  const [related, reviewsBlock, settings] = await Promise.all([getRelatedTours(tour), getReviewsBlock(), getSettings()]);
  const images = [...(tour.cover ? [{ url: tour.cover, alt: tour.title }] : []), ...tour.images.filter((i) => i.url !== tour.cover)];
  const typeLabel = TOUR_TYPE_LABELS[tour.type];
  const typeHref = TOUR_TYPE_PATHS[tour.type];

  const tabs: TabItem[] = tour.sections.map((s) => ({ key: s.key, title: s.title, content: <RichText html={s.html} /> }));
  if (tour.documents.length) {
    const docsTab = tabs.find((t) => t.key === "for_parents");
    const docsList = (
      <ul className="mt-6 grid gap-2 sm:grid-cols-2">
        {tour.documents.map((d) => (
          <li key={d.id}><a href={d.url} target="_blank" rel="noopener" className="flex items-center gap-2 rounded-2xl bg-brand-50 px-4 py-3 text-sm font-bold text-brand-700 hover:bg-brand-100"><FileDown className="h-4 w-4 shrink-0" />{d.label}</a></li>
        ))}
      </ul>
    );
    if (docsTab) docsTab.content = <>{docsTab.content}{docsList}</>; else tabs.push({ key: "docs", title: "Документы", content: docsList });
  }
  tabs.push({ key: "reviews", title: "Отзывы", content: <ReviewsBlock platforms={reviewsBlock.platforms} reviews={[...tour.reviews, ...reviewsBlock.reviews].slice(0, 6)} tourId={tour.id} compact /> });

  return (
    <div className="container-site py-6 sm:py-8">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(tourJsonLd(tour)) }} />
      <Breadcrumbs items={[{ name: "Все туры", href: "/tour/" }, { name: typeLabel, href: typeHref }, { name: tour.title }]} />
      <div className="flex flex-wrap items-center gap-2">
        {tour.isBest ? <Badge tone="sun">★ Выбор родителей</Badge> : null}
        {tour.seasons.map((s) => <Badge key={s} tone={SEASON_TONE[s] ?? "brand"}>{SEASON_LABELS[s]}{tour.seasonText ? "" : ""}</Badge>)}
        {tour.region ? <Badge tone="ink">{tour.region.name}</Badge> : null}
      </div>
      <h1 className="mt-3 text-3xl font-black leading-tight text-ink-900 sm:text-4xl lg:text-5xl">{tour.title}</h1>
      <div className="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-ink-600">
        {tour.address ? <span className="inline-flex items-center gap-1.5"><MapPin className="h-4 w-4 text-coral-500" />{tour.address}</span> : null}
        {tour.ageText ? <span className="inline-flex items-center gap-1.5"><Users className="h-4 w-4 text-berry-500" />{tour.ageText}</span> : null}
        {tour.seasonText ? <span className="inline-flex items-center gap-1.5"><CalendarRange className="h-4 w-4 text-mint-500" />{tour.seasonText}</span> : null}
        {tour.website ? <a href={tour.website} target="_blank" rel="noopener nofollow" className="inline-flex items-center gap-1.5 text-brand-600 hover:underline"><ExternalLink className="h-4 w-4" />Сайт лагеря</a> : null}
      </div>
      <div className="mt-6 grid gap-8 lg:grid-cols-12">
        <div className="lg:col-span-8">
          <TourGallery images={images} title={tour.title} />
          {tour.short ? <p className="mt-6 text-lg leading-relaxed text-ink-700">{tour.short}</p> : null}
          {tour.tags.length ? (
            <div className="mt-4 flex flex-wrap gap-2">
              {tour.tags.map((t) => <Link key={t.tag.slug} href={`/tour/filter/${t.tag.slug}/`} className="chip bg-white text-ink-700 shadow-card hover:bg-brand-50 hover:text-brand-700">{t.tag.label}</Link>)}
            </div>
          ) : null}
          <div className="mt-8 lg:hidden"><BookingWidget tourId={tour.id} tourTitle={tour.title} shifts={tour.shifts} priceText={tour.priceText} priceMin={tour.priceMin} /></div>
          <Tabs items={tabs} className="mt-8" />
        </div>
        <div className="hidden lg:col-span-4 lg:block"><BookingWidget tourId={tour.id} tourTitle={tour.title} shifts={tour.shifts} priceText={tour.priceText} priceMin={tour.priceMin} /></div>
      </div>
      {related.length ? (
        <section className="mt-14">
          <SectionTitle accent="Похожие">предложения</SectionTitle>
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">{related.map((t) => <TourCard key={t.id} tour={t} />)}</div>
        </section>
      ) : null}
      <p className="mt-10 text-center text-xs text-ink-400">Вопросы по программе: {settings["contacts.phone1"]} · {settings["contacts.email"]}</p>
    </div>
  );
}
