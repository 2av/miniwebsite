import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, RefreshCw, Search, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import {
  downloadManageUsersCsv,
  fetchDashboardDetails,
  fetchManageUsers,
  fetchReferralDetails,
  mapDeal,
  removeDeal,
  resetPassword,
  setCollaboration,
  setRefund,
  setSaleskit,
  upsertBankDetails,
} from '@/features/manage-users/api'
import type { ManageUserRow, ManageUsersQuery } from '@/shared/types/api'
import { useToast } from '@/shared/ui/Toast'
import { Badge, Button, Card, Input, Modal, Select, Toggle } from '@/shared/ui/primitives'
import { ApiError } from '@/shared/api/client'

const emptyFilters: ManageUsersQuery = {
  page: 1,
  pageSize: 10,
  search: '',
}

function paymentTone(label: string): 'neutral' | 'ok' | 'warn' {
  if (label.startsWith('Paid')) return 'ok'
  if (label === 'Not Eligible') return 'neutral'
  return 'warn'
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

export function ManageUsersPage() {
  const toast = useToast()
  const qc = useQueryClient()
  const [filters, setFilters] = useState<ManageUsersQuery>(emptyFilters)
  const [searchInput, setSearchInput] = useState('')
  const [dashboardEmail, setDashboardEmail] = useState<string | null>(null)
  const [referralEmail, setReferralEmail] = useState<string | null>(null)
  const [resetTarget, setResetTarget] = useState<string | null>(null)
  const [resetPw, setResetPw] = useState('')
  const [resetConfirm, setResetConfirm] = useState('')

  // Debounce search → API (name / email / phone)
  useEffect(() => {
    const t = window.setTimeout(() => {
      const next = searchInput.trim()
      setFilters((prev) => {
        if ((prev.search || '') === next && (prev.page ?? 1) === 1) return prev
        return { ...prev, search: next || undefined, page: 1 }
      })
    }, 350)
    return () => window.clearTimeout(t)
  }, [searchInput])

  const queryKey = useMemo(() => ['manage-users', filters] as const, [filters])

  const listQuery = useQuery({
    queryKey,
    queryFn: () =>
      fetchManageUsers({
        ...filters,
        search: filters.search || undefined,
      }),
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

  const invalidate = () => qc.invalidateQueries({ queryKey: ['manage-users'] })

  const run = async (fn: () => Promise<unknown>, okMsg: string) => {
    try {
      await fn()
      toast.push(okMsg, 'success')
      await invalidate()
    } catch (e) {
      toast.push(e instanceof ApiError ? e.message : 'Request failed', 'error')
    }
  }

  const mapDealMut = useMutation({
    mutationFn: ({ email, dealId }: { email: string; dealId: number }) => mapDeal(email, dealId),
    onSuccess: async () => {
      toast.push('Deal mapped', 'success')
      await invalidate()
    },
    onError: (e: Error) => toast.push(e.message, 'error'),
  })

  const data = listQuery.data
  const users = data?.users ?? []
  const pages = data ? Math.max(1, Math.ceil(data.totalCount / data.pageSize)) : 1
  const hasSearch = Boolean(searchInput.trim())

  return (
    <div className="flex h-full min-h-0 min-w-0 max-w-full flex-col gap-3">
      <div className="flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold text-slate-900">Manage Users</h1>
          <p className="mt-1 text-sm text-slate-500">
            {data?.totalCount ?? '—'} customers · MW deals {data?.mwDealCount ?? 0} · Franchise deals{' '}
            {data?.franchiseDealCount ?? 0}
          </p>
        </div>
        <div className="flex w-full max-w-xl flex-1 flex-wrap items-center justify-end gap-2 sm:w-auto">
          <div className="relative min-w-[220px] flex-1">
            <Search size={16} className="pointer-events-none absolute top-2.5 left-3 text-slate-400" />
            <Input
              className="pr-9 pl-9"
              placeholder="Search email, name, or phone…"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              aria-label="Search by email, name, or phone"
            />
            {hasSearch && (
              <button
                type="button"
                className="absolute top-2 right-2 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                onClick={() => setSearchInput('')}
                aria-label="Clear search"
              >
                <X size={14} />
              </button>
            )}
          </div>
          <Button variant="secondary" onClick={() => listQuery.refetch()}>
            <RefreshCw size={16} /> Refresh
          </Button>
          <Button
            variant="secondary"
            onClick={() =>
              downloadManageUsersCsv({
                ...filters,
                search: filters.search || undefined,
              }).catch((e) => toast.push(e.message, 'error'))
            }
          >
            <Download size={16} /> Export
          </Button>
        </div>
      </div>

      <Card className="flex min-h-0 min-w-0 max-w-full flex-1 flex-col overflow-hidden">
        <div className="min-h-0 flex-1 overflow-auto overscroll-contain">
          <table className="w-max min-w-full text-left text-sm">
            <thead className="sticky top-0 z-10 bg-slate-900 text-xs tracking-wide text-slate-200 uppercase">
              <tr>
                {[
                  'ID',
                  'Email',
                  'Name',
                  'Phone',
                  'State',
                  'Joined',
                  'Referral',
                  'MW',
                  'Pending ₹',
                  'MW Pay',
                  'Dashboard',
                  'Referrals',
                  'Deal MW',
                  'Deal FR',
                  'Sales Kit',
                  'Collab',
                  'Refund',
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
                  <td colSpan={18} className="px-4 py-10 text-center text-slate-500">
                    Loading users…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td colSpan={18} className="px-4 py-10 text-center text-rose-600">
                    {(listQuery.error as Error).message}
                    <div className="mt-1 text-xs text-slate-500">Start the .NET API on localhost:5209</div>
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && !listQuery.isError && users.length === 0 && (
                <tr>
                  <td colSpan={18} className="px-4 py-10 text-center text-slate-500">
                    No users found
                  </td>
                </tr>
              )}
              {users.map((u) => (
                <UserRow
                  key={u.id}
                  user={u}
                  mwDeals={data?.mwDeals ?? []}
                  franchiseDeals={data?.franchiseDeals ?? []}
                  onMapDeal={(dealId) => mapDealMut.mutate({ email: u.email, dealId })}
                  onRemoveDeal={(id) => run(() => removeDeal(id), 'Deal removed')}
                  onSaleskit={(yes) =>
                    run(() => setSaleskit(u.email, yes ? 'YES' : 'NO'), 'Sales Kit updated')
                  }
                  onCollab={(yes) =>
                    run(() => setCollaboration(u.email, yes ? 'YES' : 'NO'), 'Collaboration updated')
                  }
                  onRefund={(status) => run(() => setRefund(u.email, status), 'Refund updated')}
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
            <Button
              variant="secondary"
              disabled={(filters.page ?? 1) <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: Math.max(1, (f.page ?? 1) - 1) }))}
            >
              Prev
            </Button>
            <Button
              variant="secondary"
              disabled={(filters.page ?? 1) >= pages}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
            >
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

function UserRow({
  user: u,
  mwDeals,
  franchiseDeals,
  onMapDeal,
  onRemoveDeal,
  onSaleskit,
  onCollab,
  onRefund,
  onDashboard,
  onReferral,
  onReset,
}: {
  user: ManageUserRow
  mwDeals: { id: number; dealName: string }[]
  franchiseDeals: { id: number; dealName: string }[]
  onMapDeal: (dealId: number) => void
  onRemoveDeal: (mappingId: number) => void
  onSaleskit: (yes: boolean) => void
  onCollab: (yes: boolean) => void
  onRefund: (status: string) => void
  onDashboard: () => void
  onReferral: () => void
  onReset: () => void
}) {
  return (
    <tr className="border-t border-slate-100 align-middle hover:bg-rose-50/30">
      <td className="px-3 py-3 font-medium">{u.id}</td>
      <td className="px-3 py-3 max-w-[180px] truncate">{u.email}</td>
      <td className="px-3 py-3">{u.name}</td>
      <td className="px-3 py-3">{u.phone || '-'}</td>
      <td className="px-3 py-3">{u.state || '-'}</td>
      <td className="px-3 py-3 whitespace-nowrap text-slate-500">{formatDate(u.createdAt)}</td>
      <td className="px-3 py-3">{u.referralSourceDisplay}</td>
      <td className="px-3 py-3 text-center font-semibold">{u.websiteCount}</td>
      <td className="px-3 py-3">₹{Math.round(u.pendingReferralAmount).toLocaleString('en-IN')}</td>
      <td className="px-3 py-3">
        <Badge tone={paymentTone(u.mwPaymentStatusLabel)}>{u.mwPaymentStatusLabel}</Badge>
      </td>
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
      <td className="px-3 py-3 min-w-[160px]">
        {u.mwDeal ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800">
            {u.mwDeal.dealName.slice(0, 14)}…
            <button
              type="button"
              className="ml-1 text-rose-500"
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
              if (window.confirm(`Map this user to "${name}" for Mini Website?`)) onMapDeal(id)
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
      <td className="px-3 py-3 min-w-[160px]">
        {u.franchiseDeal ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-1 text-xs font-medium text-sky-800">
            {u.franchiseDeal.dealName.slice(0, 14)}…
            <button
              type="button"
              className="ml-1 text-sky-500"
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
              if (window.confirm(`Map this user to "${name}" for Franchise?`)) onMapDeal(id)
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
          checked={u.saleskitEnabled === 'YES'}
          onChange={(next) => {
            if (window.confirm(`${next ? 'Enable' : 'Disable'} Sales Kit for ${u.email}?`)) onSaleskit(next)
          }}
        />
      </td>
      <td className="px-3 py-3 text-center">
        <Toggle
          checked={u.collaborationEnabled === 'YES'}
          onChange={(next) => {
            if (window.confirm(`${next ? 'Enable' : 'Disable'} Collaboration for ${u.email}?`)) onCollab(next)
          }}
        />
      </td>
      <td className="px-3 py-3 min-w-[140px]">
        {u.refundStatus === 'Refund Settled' ? (
          <Badge tone="ok">Settled {formatDate(u.refundStatusDate)}</Badge>
        ) : (
          <Select
            value={u.refundStatus || 'None'}
            onChange={(e) => {
              const v = e.target.value
              if (window.confirm(`Set refund status to "${v}" for ${u.email}?`)) onRefund(v)
            }}
          >
            <option value="None">None</option>
            <option value="Refund Claimed">Refund Claimed</option>
            <option value="Refund Settled">Refund Settled</option>
          </Select>
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

function ReferralPanel({
  data,
  onSaveBank,
}: {
  data: NonNullable<Awaited<ReturnType<typeof fetchReferralDetails>>>
  onSaveBank: (bank: {
    accountHolderName?: string
    accountNumber?: string
    ifscCode?: string
    bankName?: string
    upiId?: string
    upiName?: string
  }) => Promise<void>
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
