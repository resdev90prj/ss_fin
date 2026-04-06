import { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import AppShell from './components/AppShell';
import LoadingState from './components/LoadingState';
import ProtectedRoute from './components/ProtectedRoute';
import { OnboardingProvider } from './onboarding/OnboardingProvider';

const LoginPage = lazy(() => import('./features/auth/LoginPage'));
const LogoutPage = lazy(() => import('./features/auth/LogoutPage'));
const DashboardPage = lazy(() => import('./features/dashboard/DashboardPage'));
const AccountsPage = lazy(() => import('./features/accounts/AccountsPage'));
const BoxesPage = lazy(() => import('./features/boxes/BoxesPage'));
const CategoriesPage = lazy(() => import('./features/categories/CategoriesPage'));
const TransactionsPage = lazy(() => import('./features/transactions/TransactionsPage'));
const WithdrawalsPage = lazy(() => import('./features/withdrawals/WithdrawalsPage'));
const DebtsPage = lazy(() => import('./features/debts/DebtsPage'));
const BudgetsPage = lazy(() => import('./features/budgets/BudgetsPage'));
const GoalsPage = lazy(() => import('./features/goals/GoalsPage'));
const TargetsPage = lazy(() => import('./features/targets/TargetsPage'));
const AgendaPage = lazy(() => import('./features/agenda/AgendaPage'));
const ImportsPage = lazy(() => import('./features/imports/ImportsPage'));
const ReportsPage = lazy(() => import('./features/reports/ReportsPage'));
const ProfilePage = lazy(() => import('./features/profile/ProfilePage'));
const UsersPage = lazy(() => import('./features/users/UsersPage'));
const ManagerClientsPage = lazy(() => import('./features/users/ManagerClientsPage'));

const routerBase = import.meta.env.BASE_URL === '/'
  ? '/'
  : import.meta.env.BASE_URL.replace(/\/$/, '');

export default function App() {
  return (
    <BrowserRouter basename={routerBase}>
      <AuthProvider>
        <OnboardingProvider>
          <Suspense
            fallback={(
              <div className="fullscreen-center">
                <LoadingState text="Carregando modulo..." />
              </div>
            )}
          >
            <Routes>
              <Route path="/login" element={<LoginPage />} />

              <Route element={<ProtectedRoute />}>
                <Route path="/logout" element={<LogoutPage />} />

                <Route element={<AppShell />}>
                  <Route index element={<DashboardPage />} />
                  <Route path="/accounts" element={<AccountsPage />} />
                  <Route path="/boxes" element={<BoxesPage />} />
                  <Route path="/categories" element={<CategoriesPage />} />
                  <Route path="/transactions" element={<TransactionsPage />} />
                  <Route path="/withdrawals" element={<WithdrawalsPage />} />
                  <Route path="/debts" element={<DebtsPage />} />
                  <Route path="/budgets" element={<BudgetsPage />} />
                  <Route path="/goals" element={<GoalsPage />} />
                  <Route path="/targets" element={<TargetsPage />} />
                  <Route path="/agenda" element={<AgendaPage />} />
                  <Route path="/imports" element={<ImportsPage />} />
                  <Route path="/reports" element={<ReportsPage />} />
                  <Route path="/manager-clients" element={<ManagerClientsPage />} />
                  <Route path="/profile" element={<ProfilePage />} />
                  <Route path="/users" element={<UsersPage />} />
                </Route>
              </Route>

              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Suspense>
        </OnboardingProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
