export type NavItem = { label: string; href: string; children?: NavItem[] };

/** Главное меню (структура сохранена с исходного сайта, ссылки — те же URL) */
export const mainNav: NavItem[] = [
  {
    label: "Лагеря России",
    href: "/tour/filter/lager/russia/",
    children: [
      { label: "Лагеря Краснодарского края", href: "/tour/filter/lager/krasnodar_region/" },
      { label: "Лагеря Крыма", href: "/tour/filter/lager/crymea/" },
      { label: "Лагеря Подмосковья", href: "/tour/filter/lager/moscow_region/" },
      { label: "Лагеря на море", href: "/tour/filter/lager/sea-camps/" },
      { label: "Лагеря на Алтае", href: "/tour/filter/lager/altay/" },
    ],
  },
  { label: "Спортивные сборы", href: "/tour/filter/sport/" },
  {
    label: "За границей",
    href: "/tour/filter/lager/foreign-camps/",
    children: [
      { label: "Лагеря Китая", href: "/tour/filter/lager/china/" },
      { label: "Языковые лагеря", href: "/tour/filter/lager/foreign-camps/lingual-camps/" },
      { label: "Спортивные лагеря", href: "/tour/filter/lager/foreign-camps/sport-camps/" },
    ],
  },
  { label: "Авиа и ЖД", href: "/tour/filter/transfer/" },
  {
    label: "Экскурсии",
    href: "/tour/filter/excursions/",
    children: [
      { label: "Алтай", href: "/tour/filter/lager/altay/" },
      { label: "Великий Новгород", href: "/tour/tur-na-6-dney-staraya-ladoga-velikiy-novgorod-pushkinskie-gory-pskov-izborsk-pechory/" },
      { label: "Карелия", href: "/tour/zimnyaya-mergubskaya-ekspeditsiya-kareliya-zima-2026/" },
      { label: "Новогодние туры", href: "/tour/filter/new-year/" },
    ],
  },
];

export const footerPagesNav: NavItem[] = [
  { label: "Родителям", href: "/rodytelyam/" },
  { label: "Агентствам", href: "/agentstvam/" },
  { label: "Трансфер до лагеря", href: "/transfer-do-lagerya/" },
  { label: "Оплата", href: "/pay/" },
  { label: "Контакты", href: "/kak-nas-nayti/" },
  { label: "Новости", href: "/novosti/" },
  { label: "Статьи", href: "/stati/" },
];

export const footerCatalogNav: NavItem[] = [
  { label: "Лагеря России", href: "/tour/filter/lager/russia/" },
  { label: "Спортивные сборы", href: "/tour/filter/sport/" },
  { label: "Детские лагеря за границей", href: "/tour/filter/lager/foreign-camps/" },
  { label: "Авиа и ЖД", href: "/tour/filter/transfer/" },
  { label: "Экскурсии", href: "/tour/filter/excursions/" },
  { label: "Все туры", href: "/tour/" },
];

export const footerTypesNav: NavItem[] = [
  { label: "Детский отдых", href: "/tour/filter/lager/" },
  { label: "Молодёжный отдых", href: "/tour/filter/lager/teenage-camps/" },
  { label: "Семейный отдых", href: "/tour/filter/family-tours/" },
];
