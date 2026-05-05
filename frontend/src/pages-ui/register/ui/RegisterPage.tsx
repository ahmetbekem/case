import Link from 'next/link';

import { RegisterForm } from '@/features/auth-register';

export function RegisterPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-md space-y-6">
        <RegisterForm />
        <p className="text-center text-sm text-slate-600">
          Hesabınız var mı?{' '}
          <Link
            href="/login"
            className="font-medium text-slate-900 underline-offset-4 hover:underline"
          >
            Giriş yapın
          </Link>
        </p>
      </div>
    </main>
  );
}
