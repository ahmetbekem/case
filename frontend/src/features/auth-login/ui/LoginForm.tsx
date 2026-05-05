'use client';

import { ClientApiError } from '@/shared/api/client';
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  Input,
  Label,
} from '@/shared/ui';

import { useLoginMutation } from '../api/loginMutation';
import { useLoginForm } from '../model/useLoginForm';

export function LoginForm() {
  const form = useLoginForm();
  const mutation = useLoginMutation();

  const serverError =
    mutation.error instanceof ClientApiError ? mutation.error.message : null;

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>Giriş Yap</CardTitle>
        <CardDescription>Hesabınıza giriş yapın</CardDescription>
      </CardHeader>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        noValidate
      >
        <CardContent className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="email">E-posta</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              disabled={mutation.isPending}
              {...form.register('email')}
            />
            {form.formState.errors.email !== undefined && (
              <p className="text-xs text-red-600">
                {form.formState.errors.email.message}
              </p>
            )}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="password">Şifre</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              disabled={mutation.isPending}
              {...form.register('password')}
            />
            {form.formState.errors.password !== undefined && (
              <p className="text-xs text-red-600">
                {form.formState.errors.password.message}
              </p>
            )}
          </div>
          {serverError !== null && (
            <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {serverError}
            </p>
          )}
        </CardContent>
        <CardFooter>
          <Button
            type="submit"
            className="w-full"
            disabled={mutation.isPending}
          >
            {mutation.isPending ? 'Giriş yapılıyor…' : 'Giriş Yap'}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}
