import type { Metadata } from "next";
import { StaticPage, staticPageMetadata } from "@/components/layout/static-page";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("personal-data");
}

export default function PersonalDataPage() {
  return <StaticPage slug="personal-data" />;
}
