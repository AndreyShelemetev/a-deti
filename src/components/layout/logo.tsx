import Link from "next/link";
import Image from "next/image";

export function Logo({ className = "" }: { className?: string }) {
  return (
    <Link href="/" className={`inline-flex shrink-0 items-center ${className}`} aria-label="А-тур — на главную">
      <Image src="/images/logo.svg" alt="А-тур" width={220} height={56} className="h-11 w-auto sm:h-12" unoptimized preload />
    </Link>
  );
}
