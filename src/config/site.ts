export const siteConfig = {
  name: "А-тур",
  shortDescription: "Туроператор по детскому отдыху",
  url: process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000",
  locale: "ru_RU",
  defaultTitle: "Туроператор по детскому отдыху А-тур",
  defaultDescription:
    "А-тур — туристический оператор детского, молодежного и семейного отдыха. Детские лагеря в России и за рубежом, спортивные сборы, экскурсии, трансфер.",
  reestr: { number: "РТО 000363", url: "https://tourism.gov.ru/reestry/reestr-turoperatorov/show.php?id=110192" },
};

export const TOUR_TYPE_LABELS: Record<string, string> = {
  CAMP: "Лагеря отдыха",
  FAMILY: "Семейный отдых",
  SPORT: "Спортивные сборы",
  EXCURSION: "Экскурсионные туры",
  TRANSFER: "Авиа и ж/д",
  YOUTH: "Молодёжные программы",
};

export const SEASON_LABELS: Record<string, string> = {
  SPRING: "Весна",
  SUMMER: "Лето",
  AUTUMN: "Осень",
  WINTER: "Зима",
};

export const TOUR_TYPE_PATHS: Record<string, string> = {
  CAMP: "/tour/filter/lager/",
  FAMILY: "/tour/filter/family-tours/",
  SPORT: "/tour/filter/sport/",
  EXCURSION: "/tour/filter/excursions/",
  TRANSFER: "/tour/filter/transfer/",
  YOUTH: "/tour/?type=YOUTH",
};

export const AGE_OPTIONS = Array.from({ length: 16 }, (_, i) => i + 3); // 3 … 18
