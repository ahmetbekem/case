import { AdminSettingsPage } from '@/pages-ui/admin-settings';
import { Footer } from '@/widgets/footer';
import { Header } from '@/widgets/header';

export const metadata = {
  title: 'Sistem Ayarları | Dehasoft',
};

export default function Page() {
  return (
    <>
      <Header />
      <main className="flex-1">
        <AdminSettingsPage />
      </main>
      <Footer />
    </>
  );
}
