import type { Metadata } from "next";
import { PostDetailPage, postMetadata } from "@/components/layout/post-pages";

export async function generateMetadata(props: PageProps<"/novosti/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  return postMetadata("NEWS", slug);
}

export default async function NewsDetail(props: PageProps<"/novosti/[slug]">) {
  const { slug } = await props.params;
  return <PostDetailPage kind="NEWS" slug={slug} />;
}
