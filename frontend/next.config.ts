import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Standalone output trims the Docker image down to the runtime
  // server + only the node_modules each route needs (~50MB vs 500MB+
  // for a full pnpm install). Required by frontend/Dockerfile.
  output: "standalone",

  // Strict mode catches double-render side-effect bugs in dev.
  reactStrictMode: true,

  // Hide `X-Powered-By: Next.js` so the version is not advertised.
  poweredByHeader: false,
};

export default nextConfig;
