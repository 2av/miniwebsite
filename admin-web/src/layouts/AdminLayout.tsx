import { NavLink, Outlet } from 'react-router-dom'
import {
  LayoutDashboard,
  Users,
  Globe2,
  LogOut,
  Menu,
  Building2,
  ShoppingBag,
  CreditCard,
  DeleteIcon,
} from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/shared/ui/primitives'

const nav = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/user-details', label: 'User Details', icon: Users },
  { to: '/miniwebsite-details', label: 'Miniwebsite Details', icon: Globe2 },
  { to: '/franchisee-details', label: 'Franchisee Details', icon: Building2 },
  { to: '/franchisee-distributor-details', label: 'Franchisee Distributor', icon: ShoppingBag },
  { to: '/all-orders', label: 'All Orders', icon: CreditCard },
  { to: '/user-deletion', label: 'User Deletion', icon: DeleteIcon },
]

export function AdminLayout() {
  const [open, setOpen] = useState(false)

  return (
    <div className="flex h-dvh w-full max-w-[100vw] overflow-hidden">
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 flex-col bg-[linear-gradient(165deg,#0f172a_0%,#1e293b_55%,#0f172a_100%)] text-slate-100 transition md:static md:translate-x-0',
          open ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        )}
      >
        <div className="border-b border-white/10 px-5 py-6">
          <div className="text-xs tracking-[0.2em] text-rose-300 uppercase">MiniWebsite</div>
        </div>

        <nav className="flex-1 space-y-1 px-3 py-4">
          {nav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                  isActive
                    ? 'bg-rose-500/20 text-white ring-1 ring-rose-400/30'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white',
                )
              }
            >
              <item.icon size={18} />
              <span className="flex-1">{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-white/10 p-4">
          <button
            type="button"
            className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-300 hover:bg-white/5"
          >
            <LogOut size={16} />
            Sign out (soon)
          </button>
        </div>
      </aside>

      {open && (
        <button type="button" className="fixed inset-0 z-30 bg-black/40 md:hidden" onClick={() => setOpen(false)} />
      )}

      <div className="flex h-full min-w-0 flex-1 flex-col overflow-hidden">
        <header className="z-20 flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/70 bg-white/75 px-4 py-3 backdrop-blur md:px-6">
          <button type="button" className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" onClick={() => setOpen(true)}>
            <Menu size={20} />
          </button>
          <div className="min-w-0">
            <div className="text-xs font-semibold tracking-wide text-rose-600 uppercase">Admin</div>
            <div className="truncate text-sm text-slate-500">Connected to MiniWebsite .NET API</div>
          </div>
          <div className="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">Dev mode</div>
        </header>

        <main className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
