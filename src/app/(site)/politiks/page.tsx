import type { Metadata } from "next";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("politiks");
}

export default function PolitiksPage() {
  return <StaticPage slug="politiks" />;
}
