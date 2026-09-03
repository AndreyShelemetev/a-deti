import type { Metadata } from "next";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("sovety-roditelyam");
}

export default function SovetyRoditelyamPage() {
  return <StaticPage slug="sovety-roditelyam" />;
}
