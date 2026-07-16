import { Navigate, Route, Routes } from 'react-router-dom'
import { AdminLayout } from '@/layouts/AdminLayout'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { ManageUsersPage } from '@/features/manage-users/ManageUsersPage'
import { ManageCardsPage } from '@/features/manage-cards/ManageCardsPage'
import { FranchiseDistributorsPage } from '@/features/franchise-distributors/FranchiseDistributorsPage'
import { FranchiseesPage } from '@/features/franchisees/FranchiseesPage'
import { AllOrdersPage } from '@/features/all-orders/AllOrdersPage'
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
        <Route path="payments" element={<PlaceholderPage title="Payments" />} />
        <Route path="deals" element={<PlaceholderPage title="Deals" />} />
        <Route path="settings" element={<PlaceholderPage title="Settings" />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
