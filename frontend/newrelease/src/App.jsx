import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import AppShell from './components/AppShell';
import ProtectedRoute from './components/ProtectedRoute';
import LoginPage from './features/auth/LoginPage';
import LogoutPage from './features/auth/LogoutPage';
import DashboardPage from './features/dashboard/DashboardPage';
import AccountsPage from './features/accounts/AccountsPage';
import CategoriesPage from './features/categories/CategoriesPage';
import TransactionsPage from './features/transactions/TransactionsPage';
import TargetsPage from './features/targets/TargetsPage';
import AgendaPage from './features/agenda/AgendaPage';
import ModulePlaceholderPage from './features/placeholders/ModulePlaceholderPage';
import { moduleRegistry } from './navigation/menu';

const routerBase = import.meta.env.BASE_URL === '/'
  ? '/'
  : import.meta.env.BASE_URL.replace(/\/$/, '');

export default function App() {
  return (
    <BrowserRouter basename={routerBase}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route element={<ProtectedRoute />}>
            <Route path="/logout" element={<LogoutPage />} />

            <Route element={<AppShell />}>
              <Route index element={<DashboardPage />} />
              <Route path="/accounts" element={<AccountsPage />} />
              <Route path="/boxes" element={<ModulePlaceholderPage module={moduleRegistry.boxes} />} />
              <Route path="/categories" element={<CategoriesPage />} />
              <Route path="/transactions" element={<TransactionsPage />} />
              <Route path="/withdrawals" element={<ModulePlaceholderPage module={moduleRegistry.withdrawals} />} />
              <Route path="/debts" element={<ModulePlaceholderPage module={moduleRegistry.debts} />} />
              <Route path="/budgets" element={<ModulePlaceholderPage module={moduleRegistry.budgets} />} />
              <Route path="/goals" element={<ModulePlaceholderPage module={moduleRegistry.goals} />} />
              <Route path="/targets" element={<TargetsPage />} />
              <Route path="/agenda" element={<AgendaPage />} />
              <Route path="/imports" element={<ModulePlaceholderPage module={moduleRegistry.imports} />} />
              <Route path="/reports" element={<ModulePlaceholderPage module={moduleRegistry.reports} />} />
              <Route path="/profile" element={<ModulePlaceholderPage module={moduleRegistry.profile} />} />
              <Route path="/users" element={<ModulePlaceholderPage module={moduleRegistry.users} />} />
            </Route>
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
