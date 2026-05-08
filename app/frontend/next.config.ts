import type { NextConfig } from "next";
import { withSentryConfig } from '@sentry/nextjs'

const nextConfig: NextConfig = {
  output: 'standalone',
  reactCompiler: true,
};

export default withSentryConfig(nextConfig, {
  silent: true,
  webpack: {
    autoInstrumentServerFunctions: false,
  },
});
