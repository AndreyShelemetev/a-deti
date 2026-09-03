import Image, { type ImageProps } from "next/image";

export const PLACEHOLDER = "/images/placeholder.svg";

/** next/image wrapper with a friendly placeholder when a picture is missing. */
export function SafeImage({ src, alt, ...props }: Omit<ImageProps, "src"> & { src?: string | null }) {
  const source = src && src.length > 0 ? src : PLACEHOLDER;
  const isSvg = source.endsWith(".svg");
  return <Image src={source} alt={alt} unoptimized={isSvg} {...props} />;
}
