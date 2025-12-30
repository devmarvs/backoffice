import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQuickLogStore } from '../state/quickLogStore'

export function AppShell() {
  const openQuickLog = useQuickLogStore((state) => state.open)
  const location = useLocation()
  const linkClass = ({ isActive }: { isActive: boolean }) =>
    `nav-link${isActive ? ' active' : ''}`
  const path = location.pathname
  const header = (() => {
    if (path.startsWith('/app/clients/')) {
      return { eyebrow: 'Client focus', title: 'Stay ahead for this client.' }
    }
    if (path.startsWith('/app/clients')) {
      return { eyebrow: 'Clients', title: 'Keep every client on track.' }
    }
    if (path.startsWith('/app/billing')) {
      return { eyebrow: 'Billing', title: 'Turn sessions into invoices.' }
    }
    if (path.startsWith('/app/settings')) {
      return { eyebrow: 'Settings', title: 'Tune your autopilot.' }
    }
    return { eyebrow: 'Today', title: 'Stay caught up in minutes.' }
  })()

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">BA</span>
          <div>
            <p className="brand-title">BackOffice</p>
            <p className="brand-subtitle">Autopilot for Tutors</p>
          </div>
        </div>
        <nav className="nav">
          <NavLink className={linkClass} to="/app/today">
            Today
          </NavLink>
          <NavLink className={linkClass} to="/app/clients">
            Clients
          </NavLink>
          <NavLink className={linkClass} to="/app/billing">
            Billing
          </NavLink>
          <NavLink className={linkClass} to="/app/settings">
            Settings
          </NavLink>
        </nav>
        <button className="button button--primary" onClick={openQuickLog}>
          Log session
        </button>
        <div className="sidebar-foot">
          <span className="status-pill">Autopilot active</span>
        </div>
      </aside>
      <main className="content">
        <header className="topbar">
          <div>
            <p className="eyebrow">{header.eyebrow}</p>
            <h1 className="page-title">{header.title}</h1>
          </div>
          <button className="button button--ghost" onClick={openQuickLog}>
            Quick log
          </button>
        </header>
        <Outlet />
      </main>
    </div>
  )
}
