import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, ExternalLink, Eye, Pencil, Plus, RefreshCw, Search, Share2, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import {
  activateFranchisee,
  createFranchisee,
  fetchFranchiseeDashboard,
  fetchFranchisees,
} from '@/features/franchisees/api'
import { resetPassword } from '@/features/manage-users/api'
import type { FranchiseeRow } from '@/shared/types/api'
import { ApiError } from '@/shared/api/client'
import { openInvoiceById } from '@/shared/lib/invoiceDownload'
import { useToast } from '@/shared/ui/Toast'
import { Badge, Button, Card, Input, Modal } from '@/shared/ui/primitives'

function formatDate(value?: string | null) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function tone(value: string): 'neutral' | 'ok' | 'warn' | 'danger' {
  if (value === 'ok') return 'ok'
  if (value === 'warn') return 'warn'
  if (value === 'danger') return 'danger'
  return 'neutral'
}

function shareUrl(url: string) {
  const text = encodeURIComponent(url)
  const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent)
  if (isMobile) window.location.href = `whatsapp://send?text=${text}`
  else window.open(`https://wa.me/?text=${text}`, '_blank')
}

export function FranchiseesPage() {
  const toast = useToast()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [showCreate, setShowCreate] = useState(false)
  const [createName, setCreateName] = useState('')
  const [createEmail, setCreateEmail] = useState('')
  const [createPhone, setCreatePhone] = useState('')
  const [createPassword, setCreatePassword] = useState('')
  const [dashboardEmail, setDashboardEmail] = useState<string | null>(null)
  const [resetTarget, setResetTarget] = useState<string | null>(null)
  const [resetPw, setResetPw] = useState('')
  const [resetConfirm, setResetConfirm] = useState('')

  useEffect(() => {
    const t = window.setTimeout(() => {
      const next = searchInput.trim()
      setSearch((prev) => {
        if (prev === next) return prev
        setPage(1)
        return next
      })
    }, 350)
    return () => window.clearTimeout(t)
  }, [searchInput])

  const filters = useMemo(() => ({ page, pageSize: 10, search: search || undefined }), [page, search])
  const queryKey = useMemo(() => ['franchisees', filters] as const, [filters])

  const listQuery = useQuery({
    queryKey,
    queryFn: () => fetchFranchisees(filters),
  })

  const dashboardQuery = useQuery({
    queryKey: ['franchisee-dashboard', dashboardEmail],
    queryFn: () => fetchFranchiseeDashboard(dashboardEmail!),
    enabled: !!dashboardEmail,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['franchisees'] })

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      const res = (await fn()) as { message?: string | null }
      toast.push(res?.message || ok, 'success')
      await invalidate()
      return true
    } catch (e) {
      toast.push(e instanceof ApiError ? e.message : 'Request failed', 'error')
      return false
    }
  }

  const data = listQuery.data
  const rows = data?.franchisees ?? []
  const pages = data ? Math.max(1, Math.ceil(data.totalCount / data.pageSize)) : 1
  const hasSearch = Boolean(searchInput.trim())

  return (
    <div className="flex h-full min-h-0 min-w-0 max-w-full flex-col gap-3">
      <div className="flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold text-slate-900">
            Franchisee Details
          </h1>
          <p className="mt-1 text-sm text-slate-500">{data?.totalCount ?? '—'} franchisees</p>
        </div>
        <div className="flex w-full max-w-2xl flex-1 flex-wrap items-center justify-end gap-2">
          <div className="relative min-w-[220px] flex-1">
            <Search size={16} className="pointer-events-none absolute top-2.5 left-3 text-slate-400" />
            <Input
              className="pr-9 pl-9"
              placeholder="Search email / name / phone…"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
            />
            {hasSearch && (
              <button
                type="button"
                className="absolute top-2 right-2 rounded-md p-1 text-slate-400 hover:bg-slate-100"
                onClick={() => setSearchInput('')}
              >
                <X size={14} />
              </button>
            )}
          </div>
          <Button variant="secondary" onClick={() => listQuery.refetch()}>
            <RefreshCw size={16} /> Refresh
          </Button>
          <Button onClick={() => setShowCreate(true)}>
            <Plus size={16} /> Add Franchisee
          </Button>
        </div>
      </div>

      <Card className="flex min-h-0 min-w-0 max-w-full flex-1 flex-col overflow-hidden">
        <div className="min-h-0 flex-1 overflow-auto overscroll-contain">
          <table className="w-max min-w-full text-left text-sm">
            <thead className="sticky top-0 z-10 bg-slate-900 text-xs tracking-wide text-slate-200 uppercase">
              <tr>
                {[
                  'FR ID',
                  'Email',
                  'Name',
                  'Phone',
                  'Joined',
                  'Referral',
                  'Company',
                  'FR Status',
                  'View / Edit / Share',
                  'Payment',
                  'Franchise Fee',
                  'Invoice',
                  'MW',
                  'Documents',
                  'Wallet',
                  'Dashboard',
                  'Password',
                ].map((h) => (
                  <th key={h} className="px-3 py-3 font-semibold whitespace-nowrap">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {listQuery.isLoading && (
                <tr>
                  <td colSpan={17} className="px-4 py-10 text-center text-slate-500">
                    Loading…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td colSpan={17} className="px-4 py-10 text-center text-rose-600">
                    {(listQuery.error as Error).message}
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && !listQuery.isError && rows.length === 0 && (
                <tr>
                  <td colSpan={17} className="px-4 py-10 text-center text-slate-500">
                    No franchisees found
                  </td>
                </tr>
              )}
              {rows.map((f) => (
                <Row
                  key={f.id}
                  franchisee={f}
                  onActivate={() => {
                    if (!window.confirm(`Activate franchisee ${f.email}?`)) return
                    void run(() => activateFranchisee(f.id), 'Franchisee activated')
                  }}
                  onDashboard={() => setDashboardEmail(f.email)}
                  onReset={() => {
                    setResetTarget(f.email)
                    setResetPw('')
                    setResetConfirm('')
                  }}
                />
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex shrink-0 items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
          <div className="text-slate-500">
            Page {data?.page ?? 1} of {pages} · {data?.totalCount ?? 0} · 10 per page
          </div>
          <div className="flex gap-2">
            <Button
              variant="secondary"
              disabled={page <= 1 || listQuery.isFetching}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              Prev
            </Button>
            <Button
              variant="secondary"
              disabled={page >= pages || listQuery.isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      </Card>

      <Modal open={showCreate} title="Add Franchisee" onClose={() => setShowCreate(false)}>
        <div className="space-y-3">
          <Input placeholder="Full name *" value={createName} onChange={(e) => setCreateName(e.target.value)} />
          <Input
            type="email"
            placeholder="Login email *"
            value={createEmail}
            onChange={(e) => setCreateEmail(e.target.value)}
          />
          <Input
            placeholder="Mobile (10 digits) *"
            value={createPhone}
            onChange={(e) => setCreatePhone(e.target.value.replace(/\D/g, '').slice(0, 10))}
          />
          <Input
            type="password"
            placeholder="Password *"
            value={createPassword}
            onChange={(e) => setCreatePassword(e.target.value)}
          />
          <Button
            onClick={async () => {
              if (!createName.trim() || !createEmail.trim() || createPhone.length !== 10 || !createPassword) {
                toast.push('Please fill all required fields correctly', 'error')
                return
              }
              const ok = await run(
                () =>
                  createFranchisee({
                    name: createName.trim(),
                    email: createEmail.trim(),
                    phone: createPhone,
                    password: createPassword,
                  }),
                'Franchisee created',
              )
              if (ok) {
                setShowCreate(false)
                setCreateName('')
                setCreateEmail('')
                setCreatePhone('')
                setCreatePassword('')
              }
            }}
          >
            Create ID & Password
          </Button>
        </div>
      </Modal>

      <Modal
        open={!!dashboardEmail}
        title={`Dashboard — ${dashboardEmail ?? ''}`}
        onClose={() => setDashboardEmail(null)}
        wide
      >
        {dashboardQuery.isLoading && <p className="text-slate-500">Loading…</p>}
        {dashboardQuery.isError && (
          <p className="text-rose-600">{(dashboardQuery.error as Error).message}</p>
        )}
        {dashboardQuery.data && (
          <div className="overflow-x-auto">
            {dashboardQuery.data.websites.length === 0 ? (
              <p className="rounded-xl bg-sky-50 px-4 py-3 text-sky-900">No websites created yet.</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="text-left text-slate-500">
                  <tr>
                    <th className="py-2">MW ID</th>
                    <th>Company</th>
                    <th>Created</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Open</th>
                  </tr>
                </thead>
                <tbody>
                  {dashboardQuery.data.websites.map((w) => (
                    <tr key={w.id} className="border-t border-slate-100">
                      <td className="py-2">{w.id}</td>
                      <td>{w.companyName || 'Unnamed'}</td>
                      <td>{formatDate(w.uploadedDate)}</td>
                      <td>{formatDate(w.validityDate)}</td>
                      <td>
                        <Badge tone={w.statusText === 'Active' ? 'ok' : 'warn'}>{w.statusText}</Badge>
                      </td>
                      <td>{w.paymentLabel}</td>
                      <td>
                        <a href={w.publicUrl} target="_blank" rel="noreferrer" className="text-rose-600 hover:underline">
                          View
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </Modal>

      <Modal open={!!resetTarget} title="Reset password" onClose={() => setResetTarget(null)}>
        <p className="mb-3 text-sm text-slate-500">
          Updating password for <strong>{resetTarget}</strong>
        </p>
        <div className="space-y-3">
          <Input type="password" placeholder="New password (min 6)" value={resetPw} onChange={(e) => setResetPw(e.target.value)} />
          <Input
            type="password"
            placeholder="Confirm password"
            value={resetConfirm}
            onChange={(e) => setResetConfirm(e.target.value)}
          />
          <Button
            variant="danger"
            onClick={async () => {
              if (!resetTarget) return
              if (resetPw.length < 6) return toast.push('Password must be at least 6 characters', 'error')
              if (resetPw !== resetConfirm) return toast.push('Passwords do not match', 'error')
              if (!window.confirm(`Reset password for ${resetTarget}?`)) return
              const ok = await run(
                () => resetPassword(resetTarget, resetPw, 'FRANCHISEE'),
                'Password updated',
              )
              if (ok) setResetTarget(null)
            }}
          >
            Update Password
          </Button>
        </div>
      </Modal>
    </div>
  )
}

function Row({
  franchisee: f,
  onActivate,
  onDashboard,
  onReset,
}: {
  franchisee: FranchiseeRow
  onActivate: () => void
  onDashboard: () => void
  onReset: () => void
}) {
  return (
    <tr className="border-t border-slate-100 align-middle hover:bg-rose-50/30">
      <td className="px-3 py-3 font-medium">{f.id}</td>
      <td className="px-3 py-3 max-w-[180px] truncate">{f.email}</td>
      <td className="px-3 py-3">{f.name}</td>
      <td className="px-3 py-3">{f.phone || '-'}</td>
      <td className="px-3 py-3 whitespace-nowrap text-slate-500">{formatDate(f.createdAt)}</td>
      <td className="px-3 py-3">{f.referralSourceDisplay}</td>
      <td className="px-3 py-3 max-w-[140px] truncate">{f.companyName}</td>
      <td className="px-3 py-3">
        <div className="flex flex-col items-start gap-1">
          <Badge tone={f.isActive ? 'ok' : 'neutral'}>{f.status}</Badge>
          {!f.isActive && (
            <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={onActivate}>
              Activate
            </Button>
          )}
        </div>
      </td>
      <td className="px-3 py-3">
        {f.firstCardId ? (
          <div className="flex items-center gap-1">
            <a href={f.publicUrl} target="_blank" rel="noreferrer" className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" title="View">
              <Eye size={14} />
            </a>
            <a href={f.editUrl} target="_blank" rel="noreferrer" className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" title="Edit">
              <Pencil size={14} />
            </a>
            <button
              type="button"
              className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
              title="Share"
              onClick={() => shareUrl(f.publicUrl)}
            >
              <Share2 size={14} />
            </button>
            <a href={f.publicUrl} target="_blank" rel="noreferrer" className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" title="Open">
              <ExternalLink size={14} />
            </a>
          </div>
        ) : (
          <span className="text-slate-300">-</span>
        )}
      </td>
      <td className="px-3 py-3">
        <div className="flex flex-col gap-0.5">
          <Badge tone={tone(f.paymentStatusTone)}>{f.paymentStatusLabel}</Badge>
          {f.paidOnDisplay && <span className="text-xs text-slate-500">on {f.paidOnDisplay}</span>}
        </div>
      </td>
      <td className="px-3 py-3 whitespace-nowrap">{f.franchiseFeeDisplay}</td>
      <td className="px-3 py-3">
        {f.franchiseInvoiceId ? (
          <button
            type="button"
            className="inline-flex text-rose-600 hover:underline"
            title="Download invoice"
            onClick={() => openInvoiceById(f.franchiseInvoiceId!)}
          >
            <Download size={16} />
          </button>
        ) : (
          <span className="text-slate-300">-</span>
        )}
      </td>
      <td className="px-3 py-3 text-center font-semibold">{f.websiteCount}</td>
      <td className="px-3 py-3">
        <Badge tone={tone(f.documentStatusTone)}>{f.documentStatus}</Badge>
      </td>
      <td className="px-3 py-3 whitespace-nowrap">{f.walletBalanceDisplay}</td>
      <td className="px-3 py-3">
        {f.websiteCount > 0 ? (
          <Button variant="ghost" onClick={onDashboard}>
            View
          </Button>
        ) : (
          <span className="text-slate-300">-</span>
        )}
      </td>
      <td className="px-3 py-3">
        <Button variant="ghost" onClick={onReset}>
          Reset
        </Button>
      </td>
    </tr>
  )
}
