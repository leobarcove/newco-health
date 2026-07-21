import { StrictMode, lazy, Suspense, type ReactNode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, Navigate, RouterProvider } from 'react-router'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { getToken } from './lib/api'
import './index.css'

// Route groups are lazy-loaded so each audience downloads only its slice
// (dev plan §5.2 — the 200 KB first-load budget).
const LoginPage = lazy(() => import('./routes/patient/LoginPage').then((m) => ({ default: m.LoginPage })))
const HomePage = lazy(() => import('./routes/patient/HomePage').then((m) => ({ default: m.HomePage })))
const IntakePage = lazy(() => import('./routes/patient/IntakePage').then((m) => ({ default: m.IntakePage })))
const ConsultPage = lazy(() => import('./routes/patient/ConsultPage').then((m) => ({ default: m.ConsultPage })))
const BookAppointmentPage = lazy(() =>
  import('./routes/patient/BookAppointmentPage').then((m) => ({ default: m.BookAppointmentPage })),
)
const AppointmentsPage = lazy(() =>
  import('./routes/patient/AppointmentsPage').then((m) => ({ default: m.AppointmentsPage })),
)
const ProgrammesPage = lazy(() =>
  import('./routes/patient/ProgrammesPage').then((m) => ({ default: m.ProgrammesPage })),
)
const QueuePage = lazy(() => import('./routes/doctor/QueuePage').then((m) => ({ default: m.QueuePage })))
const DoctorConsultPage = lazy(() =>
  import('./routes/doctor/DoctorConsultPage').then((m) => ({ default: m.DoctorConsultPage })),
)
const SponsorLoginPage = lazy(() =>
  import('./routes/sponsor/SponsorLoginPage').then((m) => ({ default: m.SponsorLoginPage })),
)
const SponsorDashboardPage = lazy(() =>
  import('./routes/sponsor/SponsorDashboardPage').then((m) => ({ default: m.SponsorDashboardPage })),
)
const AgendaPage = lazy(() => import('./routes/doctor/AgendaPage').then((m) => ({ default: m.AgendaPage })))
const AvailabilityPage = lazy(() =>
  import('./routes/doctor/AvailabilityPage').then((m) => ({ default: m.AvailabilityPage })),
)
const EarningsPage = lazy(() => import('./routes/doctor/EarningsPage').then((m) => ({ default: m.EarningsPage })))
const PharmacyLoginPage = lazy(() =>
  import('./routes/pharmacy/PharmacyLoginPage').then((m) => ({ default: m.PharmacyLoginPage })),
)
const PharmacyPortalPage = lazy(() =>
  import('./routes/pharmacy/PharmacyPortalPage').then((m) => ({ default: m.PharmacyPortalPage })),
)

function RequireAuth({ children }: { children: ReactNode }) {
  if (getToken() === null) return <Navigate to="/login" replace />
  return children
}

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/', element: <RequireAuth><HomePage /></RequireAuth> },
  { path: '/intake', element: <RequireAuth><IntakePage /></RequireAuth> },
  { path: '/consult/:id', element: <RequireAuth><ConsultPage /></RequireAuth> },
  { path: '/book', element: <RequireAuth><BookAppointmentPage /></RequireAuth> },
  { path: '/appointments', element: <RequireAuth><AppointmentsPage /></RequireAuth> },
  { path: '/programmes', element: <RequireAuth><ProgrammesPage /></RequireAuth> },
  { path: '/doctor', element: <RequireAuth><QueuePage /></RequireAuth> },
  { path: '/doctor/consult/:id', element: <RequireAuth><DoctorConsultPage /></RequireAuth> },
  { path: '/doctor/agenda', element: <RequireAuth><AgendaPage /></RequireAuth> },
  { path: '/doctor/availability', element: <RequireAuth><AvailabilityPage /></RequireAuth> },
  { path: '/doctor/earnings', element: <RequireAuth><EarningsPage /></RequireAuth> },
  { path: '/sponsor/login', element: <SponsorLoginPage /> },
  { path: '/sponsor', element: <RequireAuth><SponsorDashboardPage /></RequireAuth> },
  { path: '/pharmacy/login', element: <PharmacyLoginPage /> },
  { path: '/pharmacy', element: <RequireAuth><PharmacyPortalPage /></RequireAuth> },
])

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 10_000,
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <Suspense fallback={<div className="flex min-h-dvh items-center justify-center text-slate-400">Loading…</div>}>
        <RouterProvider router={router} />
      </Suspense>
    </QueryClientProvider>
  </StrictMode>,
)
