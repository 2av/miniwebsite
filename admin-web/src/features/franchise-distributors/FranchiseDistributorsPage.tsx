import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, RefreshCw, Search, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchFranchiseDistributors, setInfluencer } from '@/features/franchise-distributors/api'
import {
  fetchDashboardDetails,
  fetchReferralDetails,
  mapDeal,
  removeDeal,
  resetPassword,
  setCollaboration,
  upsertBankDetails,
} from '@/features/manage-users/api'
import type { BankDetails, FranchiseDistributorRow } from '@/shared/types/api'
import { ApiError } from '@/shared/api/client'
import { useToast } from '@/shared/ui/Toast'
import { Badge, Button, Card, Input, Modal, Select, Toggle } from '@/shared/ui/primitives'

function formatDate(value?: string | null) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function invoiceUrl(invoiceId: number) {
  const phpBase = import.meta.env.VITE_PHP_SITE_URL || 'https://miniwebsite.in'
  return `${phpBase.replace(/\/$/, '')}/admin/invoice_admin_access.php?invoice_id=${invoiceId}`
}

function cardsSearchUrl(email: string) {
  return `/miniwebsite-details?search=${encodeURIComponent(email)}`
}

export function FranchiseDistributorsPage() {
  const toast = useToast()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [dashboardEmail, setDashboardEmail] = useState<string | null>(null)
  const [referralEmail, setReferralEmail] = useState<string | null>(null)
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

  const filters = useMemo(() => ({ page, pageSize: 15, search: search || undefined }), [page, search])
  const queryKey = useMemo(() => ['franchise-distributors', filters] as const, [filters])

  const listQuery = useQuery({
    queryKey,
    queryFn: () => fetchFranchiseDistributors(filters),
  })

  const dashboardQuery = useQuery({
    queryKey: ['dashboard-details', dashboardEmail],
    queryFn: () => fetchDashboardDetails(dashboardEmail!),
    enabled: !!dashboardEmail,
  })

  const referralQuery = useQuery({
    queryKey: ['referral-details', referralEmail],
    queryFn: () => fetchReferralDetails(referralEmail!),
    enabled: !!referralEmail,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['franchise-distributors'] })

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn()
      toast.push(ok, 'success')
      await invalidate()
    } catch (e) {
      toast.push(e instanceof ApiError ? e.message : 'Request failed', 'error')
    }
  }

  const data = listQuery.data
  const users = data?.users ?? []
  const pages = data ? Math.max(1, Math.ceil(data.totalCount / data.pageSize)) : 1
  const hasSearch = Boolean(searchInput.trim())

  return (
    <div className="flex h-full min-h-0 min-w-0 max-w-full flex-col gap-3">
      <div className="flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold text-slate-900">
            Franchise Details
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            Collaboration-enabled customers · {data?.totalCount ?? '—'} total
          </p>
        </div>
        <div className="flex w-full max-w-xl flex-1 flex-wrap items-center justify-end gap-2">
          <div className="relative min-w-[220px] flex-1">
            <Search size={16} className="pointer-events-none absolute top-2.5 left-3 text-slate-400" />
            <Input
              className="pr-9 pl-9"
              placeholder="Search name / email / phone…"
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
        </div>
      </div>

      <Card className="flex min-h-0 min-w-0 max-w-full flex-1 flex-col overflow-hidden">
        <div className="min-h-0 flex-1 overflow-auto overscroll-contain">
          <table className="w-max min-w-full text-left text-sm">
            <thead className="sticky top-0 z-10 bg-slate-900 text-xs tracking-wide text-slate-200 uppercase">
              <tr>
                {[
                  'User ID',
                  'Email',
                  'Name',
                  'Phone',
                  'Joined',
                  'Referral',
                  'Company',
                  'FRD Status',
                  'Influencer',
                  'Open Cards',
                  'Card Pay',
                  'FRD Fee',
                  'Invoice',
                  'MW',
                  'Pending ₹',
                  'Dashboard',
                  'Referrals',
                  'Deal MW',
                  'Deal FR',
                  'Collab',
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
                  <td colSpan={21} className="px-4 py-10 text-center text-slate-500">
                    Loading…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td colSpan={21} className="px-4 py-10 text-center text-rose-600">
                    {(listQuery.error as Error).message}
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && !listQuery.isError && users.length === 0 && (
                <tr>
                  <td colSpan={21} className="px-4 py-10 text-center text-slate-500">
                    No collaboration-enabled users found
                  </td>
                </tr>
              )}
              {users.map((u) => (
                <Row
                  key={u.id}
                  user={u}
                  mwDeals={data?.mwDeals ?? []}
                  franchiseDeals={data?.franchiseDeals ?? []}
                  onMapDeal={(dealId) => run(() => mapDeal(u.email, dealId), 'Deal mapped')}
                  onRemoveDeal={(id) => run(() => removeDeal(id), 'Deal removed')}
                  onCollab={(yes) =>
                    run(() => setCollaboration(u.email, yes ? 'YES' : 'NO'), 'Collaboration updated')
                  }
                  onInfluencer={(status) => run(() => setInfluencer(u.email, status), 'Influencer updated')}
                  onDashboard={() => setDashboardEmail(u.email)}
                  onReferral={() => setReferralEmail(u.email)}
                  onReset={() => {
                    setResetTarget(u.email)
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
            Page {data?.page ?? 1} of {pages} · {data?.totalCount ?? 0} users
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              Prev
            </Button>
            <Button variant="secondary" disabled={page >= pages} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      </Card>

      <Modal open={!!dashboardEmail} title={`Dashboard — ${dashboardEmail ?? ''}`} onClose={() => setDashboardEmail(null)} wide>
        {dashboardQuery.isLoading && <p className="text-slate-500">Loading…</p>}
        {dashboardQuery.isError && <p className="text-rose-600">{(dashboardQuery.error as Error).message}</p>}
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
                  </tr>
                </thead>
                <tbody>
                  {dashboardQuery.data.websites.map((w) => (
                    <tr key={w.id} className="border-t border-slate-100">
                      <td className="py-2">{w.id}</td>
                      <td>{w.companyName || 'Unnamed'}</td>
                      <td>{formatDate(w.uploadedDate)}</td>
                      <td>{w.validityDisplay}</td>
                      <td>
                        <Badge tone={w.statusClass === 'bg-success' ? 'ok' : 'warn'}>{w.statusText}</Badge>
                      </td>
                      <td>{w.paymentLabel}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </Modal>

      <Modal open={!!referralEmail} title={`Referrals — ${referralEmail ?? ''}`} onClose={() => setReferralEmail(null)} wide>
        {referralQuery.isLoading && <p className="text-slate-500">Loading…</p>}
        {referralQuery.isError && <p className="text-rose-600">{(referralQuery.error as Error).message}</p>}
        {referralQuery.data && (
          <ReferralPanel
            data={referralQuery.data}
            onSaveBank={async (bank) => {
              try {
                await upsertBankDetails({ userEmail: referralQuery.data.referrerEmail, ...bank })
                toast.push('Bank details saved', 'success')
                await qc.invalidateQueries({ queryKey: ['referral-details', referralEmail] })
              } catch (e) {
                toast.push(e instanceof ApiError ? e.message : 'Failed', 'error')
              }
            }}
          />
        )}
      </Modal>

      <Modal open={!!resetTarget} title="Reset password" onClose={() => setResetTarget(null)}>
        <p className="mb-3 text-sm text-slate-500">
          Updating password for <strong>{resetTarget}</strong>
        </p>
        <div className="space-y-3">
          <Input type="password" placeholder="New password (min 6)" value={resetPw} onChange={(e) => setResetPw(e.target.value)} />
          <Input type="password" placeholder="Confirm password" value={resetConfirm} onChange={(e) => setResetConfirm(e.target.value)} />
          <Button
            variant="danger"
            onClick={async () => {
              if (!resetTarget) return
              if (resetPw.length < 6) return toast.push('Password must be at least 6 characters', 'error')
              if (resetPw !== resetConfirm) return toast.push('Passwords do not match', 'error')
              if (!window.confirm(`Reset password for ${resetTarget}?`)) return
              await run(() => resetPassword(resetTarget, resetPw), 'Password updated')
              setResetTarget(null)
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
  user: u,
  mwDeals,
  franchiseDeals,
  onMapDeal,
  onRemoveDeal,
  onCollab,
  onInfluencer,
  onDashboard,
  onReferral,
  onReset,
}: {
  user: FranchiseDistributorRow
  mwDeals: { id: number; dealName: string }[]
  franchiseDeals: { id: number; dealName: string }[]
  onMapDeal: (dealId: number) => void
  onRemoveDeal: (mappingId: number) => void
  onCollab: (yes: boolean) => void
  onInfluencer: (status: string) => void
  onDashboard: () => void
  onReferral: () => void
  onReset: () => void
}) {
  const invoiceId = u.franchiseInvoiceId || u.joiningDealInvoiceId

  return (
    <tr className="border-t border-slate-100 align-middle hover:bg-rose-50/30">
      <td className="px-3 py-3 font-medium">{u.id}</td>
      <td className="px-3 py-3 max-w-[180px] truncate">{u.email}</td>
      <td className="px-3 py-3">{u.name}</td>
      <td className="px-3 py-3">{u.phone || '-'}</td>
      <td className="px-3 py-3 whitespace-nowrap text-slate-500">{formatDate(u.createdAt)}</td>
      <td className="px-3 py-3">{u.referralSourceDisplay}</td>
      <td className="px-3 py-3 max-w-[140px] truncate">{u.companyName}</td>
      <td className="px-3 py-3">
        <Badge tone={u.frdStatusLabel === 'Active' ? 'ok' : 'neutral'}>{u.frdStatusLabel}</Badge>
      </td>
      <td className="px-3 py-3 min-w-[100px]">
        <Select
          value={u.influencer}
          onChange={(e) => {
            const v = e.target.value
            if (v === u.influencer) return
            if (!window.confirm(`${v === 'YES' ? 'Enable' : 'Disable'} influencer for ${u.email}?`)) {
              e.target.value = u.influencer
              return
            }
            onInfluencer(v)
          }}
        >
          <option value="NO">No</option>
          <option value="YES">Yes</option>
        </Select>
      </td>
      <td className="px-3 py-3">
        <Link to={cardsSearchUrl(u.email)} className="text-sm font-semibold text-rose-600 hover:underline">
          Open
        </Link>
      </td>
      <td className="px-3 py-3">{u.cardPaymentStatus}</td>
      <td className="px-3 py-3">{u.frdFeeDisplay}</td>
      <td className="px-3 py-3">
        {invoiceId ? (
          <a href={invoiceUrl(invoiceId)} target="_blank" rel="noreferrer" className="text-rose-600 hover:underline" title="Download invoice">
            <Download size={16} />
          </a>
        ) : (
          <span className="text-slate-300">-</span>
        )}
      </td>
      <td className="px-3 py-3 text-center font-semibold">{u.websiteCount}</td>
      <td className="px-3 py-3">₹{Math.round(u.pendingReferralAmount).toLocaleString('en-IN')}</td>
      <td className="px-3 py-3">
        <Button variant="ghost" onClick={onDashboard}>
          View
        </Button>
      </td>
      <td className="px-3 py-3">
        <Button variant="ghost" onClick={onReferral}>
          View
        </Button>
      </td>
      <td className="px-3 py-3 min-w-[150px]">
        {u.mwDeal ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800">
            {u.mwDeal.dealName.slice(0, 14)}…
            <button
              type="button"
              className="text-rose-500"
              onClick={() => window.confirm('Remove this deal mapping?') && onRemoveDeal(u.mwDeal!.mappingId)}
            >
              ×
            </button>
          </span>
        ) : (
          <Select
            defaultValue=""
            onChange={(e) => {
              const id = Number(e.target.value)
              if (!id) return
              const name = e.target.selectedOptions[0]?.text || 'deal'
              if (window.confirm(`Map to "${name}" for Mini Website?`)) onMapDeal(id)
              e.target.value = ''
            }}
          >
            <option value="">Select Deal</option>
            {mwDeals.map((d) => (
              <option key={d.id} value={d.id}>
                {d.dealName.slice(0, 24)}
              </option>
            ))}
          </Select>
        )}
      </td>
      <td className="px-3 py-3 min-w-[150px]">
        {u.franchiseDeal ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-1 text-xs font-medium text-sky-800">
            {u.franchiseDeal.dealName.slice(0, 14)}…
            <button
              type="button"
              className="text-sky-500"
              onClick={() => window.confirm('Remove this deal mapping?') && onRemoveDeal(u.franchiseDeal!.mappingId)}
            >
              ×
            </button>
          </span>
        ) : (
          <Select
            defaultValue=""
            onChange={(e) => {
              const id = Number(e.target.value)
              if (!id) return
              const name = e.target.selectedOptions[0]?.text || 'deal'
              if (window.confirm(`Map to "${name}" for Franchise?`)) onMapDeal(id)
              e.target.value = ''
            }}
          >
            <option value="">Select Deal</option>
            {franchiseDeals.map((d) => (
              <option key={d.id} value={d.id}>
                {d.dealName.slice(0, 24)}
              </option>
            ))}
          </Select>
        )}
      </td>
      <td className="px-3 py-3 text-center">
        <Toggle
          checked={u.collaborationEnabled === 'YES'}
          onChange={(next) => {
            if (window.confirm(`${next ? 'Enable' : 'Disable'} collaboration for ${u.email}?`)) onCollab(next)
          }}
        />
      </td>
      <td className="px-3 py-3">
        <Button variant="ghost" onClick={onReset}>
          Reset
        </Button>
      </td>
    </tr>
  )
}

function ReferralPanel({
  data,
  onSaveBank,
}: {
  data: NonNullable<Awaited<ReturnType<typeof fetchReferralDetails>>>
  onSaveBank: (bank: BankDetails) => Promise<void>
}) {
  const [edit, setEdit] = useState(false)
  const [bank, setBank] = useState({
    bankName: data.bank.bankName || '',
    accountHolderName: data.bank.accountHolderName || '',
    accountNumber: data.bank.accountNumber || '',
    ifscCode: data.bank.ifscCode || '',
    upiId: data.bank.upiId || '',
    upiName: data.bank.upiName || '',
  })

  return (
    <div className="space-y-4">
      <div>
        <div className="text-lg font-semibold">{data.userName}</div>
        <div className="text-sm text-slate-500">{data.referrerEmail}</div>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {[
          ['Pending', `₹${Math.round(data.pendingAmount).toLocaleString('en-IN')}`],
          ['Total Earning', `₹${Math.round(data.totalReferralAmount).toLocaleString('en-IN')}`],
          ['Referred MW', String(data.regularReferrals)],
          ['Referred Franchise', String(data.collaborationReferrals)],
        ].map(([k, v]) => (
          <Card key={k} className="p-3">
            <div className="text-xs text-slate-500">{k}</div>
            <div className="mt-1 text-xl font-semibold">{v}</div>
          </Card>
        ))}
      </div>

      <Card className="p-4">
        <div className="mb-3 flex items-center justify-between">
          <h4 className="font-semibold">Bank Account Details</h4>
          {!edit ? (
            <Button variant="secondary" onClick={() => setEdit(true)}>
              Edit
            </Button>
          ) : (
            <Button
              onClick={async () => {
                await onSaveBank(bank)
                setEdit(false)
              }}
            >
              Save
            </Button>
          )}
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          {(
            [
              ['bankName', 'Bank Name'],
              ['accountHolderName', 'Account Holder'],
              ['accountNumber', 'Account No.'],
              ['ifscCode', 'IFSC'],
              ['upiId', 'UPI ID'],
              ['upiName', 'UPI Name'],
            ] as const
          ).map(([key, label]) => (
            <label key={key} className="text-sm">
              <span className="mb-1 block text-slate-500">{label}</span>
              {edit ? (
                <Input value={bank[key]} onChange={(e) => setBank((b) => ({ ...b, [key]: e.target.value }))} />
              ) : (
                <div className="font-medium">{bank[key] || '-'}</div>
              )}
            </label>
          ))}
        </div>
      </Card>

      <div className="overflow-x-auto">
        <table className="min-w-[1000px] w-full text-sm">
          <thead className="text-left text-slate-500">
            <tr>
              <th className="py-2">User</th>
              <th>Email</th>
              <th>Source</th>
              <th>Joined</th>
              <th>MW Status</th>
              <th>Amount</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            {data.referredUsers.length === 0 && (
              <tr>
                <td colSpan={7} className="py-6 text-slate-500">
                  No referrals found
                </td>
              </tr>
            )}
            {data.referredUsers.map((r) => (
              <tr key={r.referralId} className="border-t border-slate-100">
                <td className="py-2">{r.userName || 'Unknown'}</td>
                <td>{r.referredEmail}</td>
                <td>{r.isCollaboration === 'YES' ? 'Franchisee' : 'MiniWebsite'}</td>
                <td>{formatDate(r.referralDate)}</td>
                <td>{r.mwStatusText}</td>
                <td>₹{Math.round(r.amount).toLocaleString('en-IN')}</td>
                <td>{r.paymentStatus === 'Success' ? 'Paid' : 'Pending'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
