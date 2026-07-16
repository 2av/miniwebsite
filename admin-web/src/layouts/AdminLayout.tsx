import { NavLink, Outlet, useLocation } from 'react-router-dom'
import {
  LayoutDashboard,
  Users,
  Globe2,
  LogOut,
  Menu,
  Building2,
  ShoppingBag,
  CreditCard,
  Delete as DeleteIcon,
  ChevronDown,
  UserRound,
  Briefcase,
  UsersRound,
  Share2,
  Tag,
  BatteryCharging,
  CircleHelp,
  FileText,
  Scale,
  Shield,
  Handshake,
  Store,
  ScrollText,
  ClipboardList,
} from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/lib/utils'

type NavItem = {
  to: string
  label: string
  icon: typeof LayoutDashboard
  end?: boolean
  children?: { to: string; label: string; icon: typeof LayoutDashboard }[]
}

const nav: NavItem[] = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/user-details', label: 'User Details', icon: Users },
  { to: '/miniwebsite-details', label: 'Miniwebsite Details', icon: Globe2 },
  { to: '/franchisee-details', label: 'Franchisee Details', icon: Building2 },
  { to: '/franchisee-distributor-details', label: 'Franchisee Distributor', icon: ShoppingBag },
  { to: '/all-orders', label: 'All Orders', icon: CreditCard },
  {
    to: '/user-deletion',
    label: 'User Deletion',
    icon: DeleteIcon,
    children: [
      { to: '/user-deletion/customer', label: 'Customer', icon: UserRound },
      { to: '/user-deletion/franchisee', label: 'Franchisee', icon: Briefcase },
      { to: '/user-deletion/team', label: 'Team', icon: UsersRound },
    ],
  },
  { to: '/manage-referrals', label: 'Manage Referrals', icon: Share2 },
  { to: '/manage-deals', label: 'Manage Deals', icon: Tag },
  { to: '/recharge-wallet', label: 'Recharge Wallet', icon: BatteryCharging },
  { to: '/manage-faqs', label: 'FAQ Management', icon: CircleHelp },
  {
    to: '/manage-content',
    label: 'Content Management',
    icon: FileText,
    children: [
      { to: '/manage-content/terms-conditions', label: 'Terms & Conditions', icon: Scale },
      { to: '/manage-content/privacy-policy', label: 'Privacy Policy', icon: Shield },
      { to: '/manage-content/franchisee-agreement', label: 'Franchisee Agreement', icon: Handshake },
      { to: '/manage-content/franchisee-distributer', label: 'Franchisee Distributer', icon: Store },
      { to: '/manage-content/mw-full-franchise-agreement', label: 'MW Full Franchise Agreement', icon: ScrollText },
      { to: '/manage-content/mw-franchisee-operation-policy', label: 'MW Franchisee Operation Policy', icon: ClipboardList },
    ],
  },
]

export function AdminLayout() {
  const [open, setOpen] = useState(false)
  const location = useLocation()
  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>(() => ({
    '/user-deletion': location.pathname.startsWith('/user-deletion'),
    '/manage-content': location.pathname.startsWith('/manage-content'),
  }))

  const toggleSection = (to: string) => {
    setExpandedSections((prev) => ({ ...prev, [to]: !prev[to] }))
  }

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

        <nav className="sidebar-nav-scroll flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {nav.map((item) => {
            if (item.children?.length) {
              const sectionActive = location.pathname.startsWith(item.to)
              const expanded = Boolean(expandedSections[item.to]) || sectionActive
              return (
                <div key={item.to} className="space-y-1">
                  <button
                    type="button"
                    onClick={() => toggleSection(item.to)}
                    className={cn(
                      'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                      sectionActive
                        ? 'bg-rose-500/20 text-white ring-1 ring-rose-400/30'
                        : 'text-slate-300 hover:bg-white/5 hover:text-white',
                    )}
                  >
                    <item.icon size={18} />
                    <span className="flex-1 text-left">{item.label}</span>
                    <ChevronDown size={16} className={cn('transition', expanded && 'rotate-180')} />
                  </button>
                  {expanded && (
                    <div className="ml-3 space-y-1 border-l border-white/10 pl-2">
                      {item.children.map((child) => (
                        <NavLink
                          key={child.to}
                          to={child.to}
                          onClick={() => setOpen(false)}
                          className={({ isActive }) =>
                            cn(
                              'flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition',
                              isActive
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-400 hover:bg-white/5 hover:text-white',
                            )
                          }
                        >
                          <child.icon size={15} />
                          {child.label}
                        </NavLink>
                      ))}
                    </div>
                  )}
                </div>
              )
            }

            return (
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
            )
          })}
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
          <div className="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">Dev mode</div>
        </header>

        <main className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
