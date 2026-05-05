import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,

  // ─── App-level rules ───────────────────────────────────────────────
  {
    rules: {
      // Backend secrets MUST NEVER cross into the client bundle.
      // SECURITY.md §3.2: any LARAVEL_API_URL, BACKEND_PROXY_SECRET,
      // or BFF helper getting imported under NEXT_PUBLIC_* / a
      // 'use client' file is a hard fail at lint time.
      "no-restricted-imports": [
        "error",
        {
          paths: [
            {
              name: "@/shared/api/server-fetch",
              message:
                "server-fetch is server-only. Use it from a route handler / Server Component, never a 'use client' file.",
            },
            {
              name: "@/shared/config/env",
              importNames: ["serverEnv"],
              message:
                "serverEnv is server-only. Public-safe values live in publicEnv from the same module.",
            },
          ],
        },
      ],

      // process.env.NEXT_PUBLIC_LARAVEL_URL would expose the backend
      // origin to the browser bundle — block direct reads.
      "no-restricted-syntax": [
        "error",
        {
          selector:
            "MemberExpression[object.object.name='process'][object.property.name='env'][property.name=/^NEXT_PUBLIC_(LARAVEL|BACKEND|API_SECRET|JWT)/]",
          message:
            "Backend URLs and secrets must NOT be exposed via NEXT_PUBLIC_* — keep them in server-only env (serverEnv).",
        },
      ],
    },
  },

  // server-fetch.ts is the one canonical caller of serverEnv — the
  // banned-imports rule is meant to fence OUT every OTHER consumer.
  // BFF route handlers (src/app/api/**) are also server-only and need
  // server-fetch + serverEnv to talk to Laravel.
  {
    files: ["src/shared/api/server-fetch.ts", "src/app/api/**/*.ts"],
    rules: {
      "no-restricted-imports": "off",
    },
  },

  // FSD layer dependency rules — upper layers may import from lower
  // ones, never the other way round, never sideways within a layer.
  // Enforced by eslint-plugin-boundaries when added in TASK 0.16; for
  // now this lives as a /* lint-comment */ contract.

  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
]);

export default eslintConfig;
