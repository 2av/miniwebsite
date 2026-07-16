import { Navigate, Route, Routes } from 'react-router-dom'
import { AdminLayout } from '@/layouts/AdminLayout'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { ManageUsersPage } from '@/features/manage-users/ManageUsersPage'
import { ManageCardsPage } from '@/features/manage-cards/ManageCardsPage'
import { FranchiseDistributorsPage } from '@/features/franchise-distributors/FranchiseDistributorsPage'
import { FranchiseesPage } from '@/features/franchisees/FranchiseesPage'
import { AllOrdersPage } from '@/features/all-orders/AllOrdersPage'
import { UserDeletionsPage } from '@/features/user-deletions/UserDeletionsPage'
import { ManageReferralsPage } from '@/features/manage-referrals/ManageReferralsPage'
import { ManageDealsPage } from '@/features/manage-deals/ManageDealsPage'
import { RechargeWalletPage } from '@/features/wallet-recharge/RechargeWalletPage'
import { ManageFaqsPage } from '@/features/manage-faqs/ManageFaqsPage'
import { ManageContentPage } from '@/features/manage-content/ManageContentPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { PlaceholderPage } from '@/features/common/PlaceholderPage'

export function AppRouter() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<AdminLayout />}>
        <Route index element={<DashboardPage />} />
        <Route path="user-details" element={<ManageUsersPage />} />
        <Route path="manage-users" element={<Navigate to="/user-details" replace />} />
        <Route path="miniwebsite-details" element={<ManageCardsPage />} />
        <Route path="franchisee-details" element={<FranchiseesPage />} />
        <Route path="franchise-details" element={<Navigate to="/franchisee-details" replace />} />
        <Route path="franchisee-distributor-details" element={<FranchiseDistributorsPage />} />
        <Route path="all-orders" element={<AllOrdersPage />} />
        <Route path="user-deletion" element={<Navigate to="/user-deletion/customer" replace />} />
        <Route path="user-deletion/customer" element={<UserDeletionsPage lockedRole="CUSTOMER" />} />
        <Route path="user-deletion/franchisee" element={<UserDeletionsPage lockedRole="FRANCHISEE" />} />
        <Route path="user-deletion/team" element={<UserDeletionsPage lockedRole="TEAM" />} />
        <Route path="manage-referrals" element={<ManageReferralsPage />} />
        <Route path="manage-deals" element={<ManageDealsPage />} />
        <Route path="deals" element={<Navigate to="/manage-deals" replace />} />
        <Route path="recharge-wallet" element={<RechargeWalletPage />} />
        <Route path="add-money" element={<Navigate to="/recharge-wallet" replace />} />
        <Route path="manage-faqs" element={<ManageFaqsPage />} />
        <Route path="manage-faq" element={<Navigate to="/manage-faqs" replace />} />
        <Route path="manage-content" element={<Navigate to="/manage-content/terms-conditions" replace />} />
        <Route path="manage-content/terms-conditions" element={<ManageContentPage contentType="terms_conditions" />} />
        <Route path="manage-content/privacy-policy" element={<ManageContentPage contentType="privacy_policy" />} />
        <Route path="manage-content/franchisee-agreement" element={<ManageContentPage contentType="franchisee_agreement" />} />
        <Route path="manage-content/franchisee-distributer" element={<ManageContentPage contentType="franchisee_distributer" />} />
        <Route
          path="manage-content/mw-full-franchise-agreement"
          element={<ManageContentPage contentType="mw_full_franchise_agreement" />}
        />
        <Route
          path="manage-content/mw-franchisee-operation-policy"
          element={<ManageContentPage contentType="mw_franchisee_operation_policy" />}
        />
        <Route path="payments" element={<PlaceholderPage title="Payments" />} />
        <Route path="settings" element={<PlaceholderPage title="Settings" />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
