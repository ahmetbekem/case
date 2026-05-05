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

import { useRegisterMutation } from '../api/registerMutation';
import { useRegisterForm } from '../model/useRegisterForm';

export function RegisterForm() {
  const form = useRegisterForm();
  const mutation = useRegisterMutation();

  const serverError =
    mutation.error instanceof ClientApiError ? mutation.error.message : null;

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>Kayıt Ol</CardTitle>
        <CardDescription>Yeni hesap oluşturun</CardDescription>
      </CardHeader>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        noValidate
      >
        <CardContent className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="fullName">Ad Soyad</Label>
            <Input
              id="fullName"
              type="text"
              autoComplete="name"
              disabled={mutation.isPending}
              {...form.register('fullName')}
            />
            {form.formState.errors.fullName !== undefined && (
              <p className="text-xs text-red-600">
                {form.formState.errors.fullName.message}
              </p>
            )}
          </div>
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
              autoComplete="new-password"
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
            {mutation.isPending ? 'Kayıt yapılıyor…' : 'Kayıt Ol'}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}
