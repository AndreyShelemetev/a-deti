import { permanentRedirect } from "next/navigation";
import { prisma } from "@/lib/prisma";

/** If a legacy URL is registered in the Redirect table, send the visitor there (301). Otherwise returns. */
export async function redirectIfLegacy(path: string): Promise<void> {
  const withSlash = path.endsWith("/") ? path : `${path}/`;
  const row = await prisma.redirect.findFirst({ where: { from: { in: [withSlash, withSlash.slice(0, -1)] } } });
  if (row) permanentRedirect(row.to);
}
