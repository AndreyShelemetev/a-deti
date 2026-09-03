import type { Metadata } from "next";
import { PostListPage, postListMetadata } from "@/components/layout/post-pages";

function pageOf(sp: Record<string, string | string[] | undefined>) {
  const v = sp.page; return Math.max(Number.parseInt((Array.isArray(v) ? v[0] : v) ?? "1", 10) || 1, 1);
}

export async function generateMetadata(props: PageProps<"/novosti">): Promise<Metadata> {
  return postListMetadata("NEWS", pageOf(await props.searchParams));
}

export default async function NewsPage(props: PageProps<"/novosti">) {
  return <PostListPage kind="NEWS" page={pageOf(await props.searchParams)} />;
}
