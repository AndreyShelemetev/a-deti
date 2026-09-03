import { prisma } from "@/lib/prisma";
import { AdminPage, Panel } from "@/components/admin/ui";
import { HomeBlocksEditor } from "./home-editor";

export default async function HomeAdmin() {
  const [slides, features, partners, platforms] = await Promise.all([
    prisma.slide.findMany({ orderBy: [{ placement: "asc" }, { sortOrder: "asc" }] }),
    prisma.feature.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.partner.findMany({ orderBy: { sortOrder: "asc" } }),
    prisma.reviewPlatform.findMany({ orderBy: { sortOrder: "asc" } }),
  ]);
  return (
    <AdminPage title="Главная страница" description="Слайдер, хиты продаж, блок «Коротко о нас», партнёры и рейтинги. «Лучшие предложения» управляются флагами ★ и 🔥 в карточках туров.">
      <Panel title="Слайдер (HERO) и хиты продаж (HIT)"><HomeBlocksEditor kind="slide" items={slides} /></Panel>
      <Panel title="Коротко о нас"><HomeBlocksEditor kind="feature" items={features} /></Panel>
      <Panel title="Партнёры"><HomeBlocksEditor kind="partner" items={partners} /></Panel>
      <Panel title="Рейтинги на площадках"><HomeBlocksEditor kind="platform" items={platforms} /></Panel>
    </AdminPage>
  );
}
