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
const QueuePage = lazy(() => import('./routes/doctor/QueuePage').then((m) => ({ default: m.QueuePage })))
const DoctorConsultPage = lazy(() =>
  import('./routes/doctor/DoctorConsultPage').then((m) => ({ default: m.DoctorConsultPage })),
)
const AgendaPage = lazy(() => import('./routes/doctor/AgendaPage').then((m) => ({ default: m.AgendaPage })))
const AvailabilityPage = lazy(() =>
  import('./routes/doctor/AvailabilityPage').then((m) => ({ default: m.AvailabilityPage })),
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
  { path: '/doctor', element: <RequireAuth><QueuePage /></RequireAuth> },
  { path: '/doctor/consult/:id', element: <RequireAuth><DoctorConsultPage /></RequireAuth> },
  { path: '/doctor/agenda', element: <RequireAuth><AgendaPage /></RequireAuth> },
  { path: '/doctor/availability', element: <RequireAuth><AvailabilityPage /></RequireAuth> },
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
