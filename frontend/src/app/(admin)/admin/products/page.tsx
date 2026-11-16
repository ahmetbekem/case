import { Footer } from '@/widgets/footer';
import { Header } from '@/widgets/header';
import { AdminProductsPage } from '@/pages-ui/admin-products';

export const metadata = {
  title: 'Ürün Yönetimi | E-Ticaret Demo',
};

/**
 * The (admin) route group reuses the shop's Header — no separate
 * admin chrome — but server-side gating still happens at the page
 * (AdminProductsPage checks role and renders the access denied
 * message itself when a non-admin somehow navigates here).
 */
export default function Page() {
  return (
    <>
      <Header />
      <main className="flex-1">
        <AdminProductsPage />
      </main>
      <Footer />
    </>
  );
}
