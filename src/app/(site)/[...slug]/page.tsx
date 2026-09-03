import { notFound } from "next/navigation";
import { redirectIfLegacy } from "@/server/queries/redirects";

/** Catch-all: serves legacy URL redirects stored in the database (renamed pages), otherwise 404. */
export default async function CatchAll(props: PageProps<"/[...slug]">) {
  const { slug } = await props.params;
  await redirectIfLegacy(`/${slug.join("/")}/`);
  notFound();
}
