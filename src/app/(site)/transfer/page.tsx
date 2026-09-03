import type { Metadata } from "next";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("transfer");
}

export default function TransferPage() {
  return <StaticPage slug="transfer" />;
}
