import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { AdminPage, LinkButton, StatusBadge, Table, Td, Th } from "@/components/admin/ui";
import { TOUR_TYPE_LABELS, SEASON_LABELS } from "@/config/site";

export default async function CatalogPagesAdmin() {
  const items = await prisma.catalogPage.findMany({ orderBy: [{ sortOrder: "asc" }, { path: "asc" }] });
  return (
    <AdminPage title="Страницы каталога (SEO)" description="Посадочные страницы /tour/filter/…/ с правилом отбора туров, собственными H1, title, description и SEO-текстом." actions={<LinkButton href="/admin/catalog-pages/new/">+ Добавить</LinkButton>}>
      <Table>
        <thead><tr><Th>Путь</Th><Th>H1</Th><Th>Правило</Th><Th>Индекс</Th></tr></thead>
        <tbody>
          {items.map((c) => (
            <tr key={c.id} className="hover:bg-brand-50/40">
              <Td><Link href={`/admin/catalog-pages/${c.id}/`} className="font-bold text-brand-600 hover:underline">/tour/filter/{c.path}/</Link></Td>
              <Td>{c.h1}</Td>
              <Td className="text-xs text-ink-500">{[c.type ? TOUR_TYPE_LABELS[c.type] : null, c.regionSlugs.length ? `регионы: ${c.regionSlugs.join(", ")}` : null, c.tagSlugs.length ? `теги: ${c.tagSlugs.join(", ")}` : null, c.season ? SEASON_LABELS[c.season] : null].filter(Boolean).join(" · ") || "все туры"}</Td>
              <Td><StatusBadge value={c.isIndexable} label={c.isIndexable ? "index" : "noindex"} /></Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </AdminPage>
  );
}
