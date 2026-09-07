import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Preserve the original site's URL scheme (all paths end with a slash) — important for SEO migration.
  trailingSlash: true,
  poweredByHeader: false,
  // "standalone" only for the Docker image; on a plain server `next start` is used (see deploy.sh)
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  serverExternalPackages: ["bcryptjs", "nodemailer"],
  images: {
    formats: ["image/avif", "image/webp"],
    qualities: [60, 75, 85],
    deviceSizes: [360, 640, 768, 1024, 1280, 1536, 1920],
    minimumCacheTTL: 60 * 60 * 24 * 30,
  },
  async redirects() {
    return [
      // Legacy Bitrix URLs
      { source: "/news/:slug.html", destination: "/novosti/:slug/", permanent: true },
      { source: "/news", destination: "/novosti/", permanent: true },
      { source: "/personal_data.php", destination: "/personal-data/", permanent: true },
      { source: "/pay/cacheback.php", destination: "/pay/cashback/", permanent: true },
      { source: "/404.php", destination: "/404", permanent: true },
      { source: "/tour/filter/:seg(type-is-.*)/:rest*", destination: "/tour/", permanent: true },
      { source: "/index.php", destination: "/", permanent: true },
    ];
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
        ],
      },
      { source: "/uploads/:path*", headers: [{ key: "Cache-Control", value: "public, max-age=31536000, immutable" }] },
    ];
  },
};

export default nextConfig;
