import type { Metadata } from "next";
import { PostDetailPage, postMetadata } from "@/components/layout/post-pages";

export async function generateMetadata(props: PageProps<"/stati/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  return postMetadata("ARTICLE", slug);
}

export default async function ArticleDetail(props: PageProps<"/stati/[slug]">) {
  const { slug } = await props.params;
  return <PostDetailPage kind="ARTICLE" slug={slug} />;
}
