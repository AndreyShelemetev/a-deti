import type { Metadata } from "next";
import { buildMetadata } from "@/lib/seo";
import { getSettings } from "@/server/queries/settings";
import { getHomeBlocks } from "@/server/queries/home";
import { getFilterFacets, getHomeTours, listTours } from "@/server/queries/tours";
import { getLatestPosts } from "@/server/queries/posts";
import { Hero } from "@/components/home/hero";
import { HitCards } from "@/components/home/hit-cards";
import { BestTours } from "@/components/home/best-tours";
import { CtaBanner } from "@/components/home/cta-banner";
import { NewsPreview } from "@/components/home/news-preview";
import { Features } from "@/components/home/features";
import { Partners } from "@/components/home/partners";
import { ReviewsBlock } from "@/components/home/reviews-block";
import { MapBlock } from "@/components/home/map-block";
import { RequestForm } from "@/components/forms/request-form";
import { SectionTitle } from "@/components/ui/section-title";

export async function generateMetadata(): Promise<Metadata> {
  const s = await getSettings();
  return buildMetadata({ title: s["home.seoTitle"] || "Туроператор по детскому отдыху А-тур", description: s["home.metaDescription"], keywords: s["home.metaKeywords"], path: "/" });
}

export default async function HomePage() {
  const [settings, blocks, tours, facets, news, all] = await Promise.all([
    getSettings(), getHomeBlocks(), getHomeTours(), getFilterFacets(), getLatestPosts("NEWS", 3), listTours({ perPage: 1 }),
  ]);
  return (
    <>
      <Hero slides={blocks.slides} facets={facets} toursCount={all.total} />
      <HitCards hits={blocks.hits} />
      <BestTours
        accent="Лучшие" title="предложения детского отдыха в России" tours={tours.bestRussia} allHref="/tour/filter/lager/russia/" tone="sand"
        links={[{ href: "/tour/filter/lager/crymea/", label: "Детские лагеря в Крыму" }, { href: "/tour/filter/lager/moscow_region/", label: "Лагеря в Подмосковье" }, { href: "/tour/filter/lager/krasnodar_region/summer/", label: "Летние лагеря Краснодарского края" }, { href: "/tour/filter/lager/sea-camps/", label: "Лагеря на море" }]}
      />
      <BestTours
        accent="Лучшие" title="предложения детского отдыха за границей" tours={tours.bestAbroad} allHref="/tour/filter/lager/foreign-camps/"
        links={[{ href: "/tour/filter/lager/foreign-camps/", label: "Все лагеря за границей" }, { href: "/tour/filter/lager/foreign-camps/sport-camps/", label: "Спортивные лагеря" }, { href: "/tour/filter/lager/foreign-camps/lingual-camps/", label: "С изучением иностранного языка" }]}
      />
      <CtaBanner ticketText={settings["home.ticketText"]} />
      <NewsPreview posts={news} />
      <Features features={blocks.features} />
      <ReviewsBlock platforms={blocks.platforms} reviews={blocks.reviews} intro={settings["reviews.intro"]} />
      <Partners partners={blocks.partners} />
      <MapBlock settings={settings} />
      <section className="container-site pb-4" id="request">
        <div className="card grid gap-8 p-6 sm:p-10 lg:grid-cols-12">
          <div className="lg:col-span-5">
            <SectionTitle accent="Отправить" subtitle="Расскажите о ребёнке и пожеланиях — подберём 2–3 подходящих варианта и ответим на вопросы.">заявку</SectionTitle>
            <ul className="space-y-2 text-sm text-ink-600">
              <li>✔ Ответим в течение рабочего дня</li>
              <li>✔ Подберём лагерь по возрасту, бюджету и интересам</li>
              <li>✔ Поможем с документами и доставкой</li>
            </ul>
          </div>
          <div className="lg:col-span-7"><RequestForm pageUrl="/" /></div>
        </div>
      </section>
    </>
  );
}
