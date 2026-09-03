import { cn } from "@/lib/utils";

/** Renders HTML stored in the CMS. Content is authored by admins (sanitised on save in the admin panel). */
export function RichText({ html, className }: { html: string; className?: string }) {
  return <div className={cn("rich", className)} dangerouslySetInnerHTML={{ __html: html }} />;
}
