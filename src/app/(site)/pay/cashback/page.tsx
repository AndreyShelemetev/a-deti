import type { Metadata } from "next";
import { staticPageMetadata } from "@/components/layout/static-page";
import { PayPage } from "@/components/layout/pay-page";

export async function generateMetadata(): Promise<Metadata> {
  return staticPageMetadata("pay-cashback", "/pay/cashback/");
}

export default function PayCashback() {
  return <PayPage cashback />;
}
