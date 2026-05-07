import Link from 'next/link';
import { Suspense } from 'react';

import { LoginForm } from '@/features/auth-login';

/**
 * LoginForm useSearchParams kullandığı için Suspense boundary şart
 * (Next 15+ static prerender uyumluluğu).
 */
export function LoginPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-md space-y-6">
        <Suspense fallback={<LoginFormSkeleton />}>
          <LoginForm />
        </Suspense>
        <p className="text-center text-sm text-slate-600">
          Hesabınız yok mu?{' '}
          <Link
            href="/register"
            className="font-medium text-slate-900 underline-offset-4 hover:underline"
          >
            Kayıt olun
          </Link>
        </p>
      </div>
    </main>
  );
}

function LoginFormSkeleton() {
  return (
    <div className="h-72 w-full max-w-md animate-pulse rounded-lg border border-slate-200 bg-white" />
  );
}
