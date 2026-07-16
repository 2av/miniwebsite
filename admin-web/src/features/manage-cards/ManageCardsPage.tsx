import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, ExternalLink, Eye, Pencil, RefreshCw, Search, Share2, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { fetchManageCards, setComplimentary } from '@/features/manage-cards/api'
import type { ManageCardRow } from '@/shared/types/api'
import { ApiError } from '@/shared/api/client'
import { openInvoiceByCardId } from '@/shared/lib/invoiceDownload'
import { useToast } from '@/shared/ui/Toast'
import { Badge, Button, Card, Input, Select, Toggle } from '@/shared/ui/primitives'

type Filters = {
  page: number
  pageSize: number
  search?: string
  paymentFilter?: string
}

const emptyFilters: Filters = { page: 1, pageSize: 10, search: '', paymentFilter: 'all' }

function formatDate(value?: string | null) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function tone(t: string): 'neutral' | 'ok' | 'warn' | 'danger' {
  if (t === 'ok') return 'ok'
  if (t === 'warn') return 'warn'
  if (t === 'danger') return 'danger'
  return 'neutral'
}

function shareCard(url: string) {
  const text = encodeURIComponent(url)
  const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent)
  if (isMobile) window.location.href = `whatsapp://send?text=${text}`
  else window.open(`https://wa.me/?text=${text}`, '_blank')
}

export function ManageCardsPage() {
  const toast = useToast()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const initialSearch = searchParams.get('search')?.trim() || ''
  const [filters, setFilters] = useState<Filters>(() => ({
    ...emptyFilters,
    search: initialSearch || undefined,
  }))
  const [searchInput, setSearchInput] = useState(initialSearch)
  const [paymentDraft, setPaymentDraft] = useState('all')

  useEffect(() => {
    const t = window.setTimeout(() => {
      const next = searchInput.trim()
      setFilters((prev) => {
        if ((prev.search || '') === next) return prev
        return { ...prev, search: next || undefined, page: 1 }
      })
    }, 350)
    return () => window.clearTimeout(t)
  }, [searchInput])

  const queryKey = useMemo(() => ['manage-cards', filters] as const, [filters])

  const listQuery = useQuery({
    queryKey,
    queryFn: () =>
      fetchManageCards({
        page: filters.page,
        pageSize: filters.pageSize,
        search: filters.search || undefined,
        paymentFilter: filters.paymentFilter && filters.paymentFilter !== 'all' ? filters.paymentFilter : undefined,
      }),
  })

  const complimentaryMut = useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'Yes' | 'No' }) => setComplimentary(id, status),
    onSuccess: async (res) => {
      toast.push(res.message || 'Complimentary updated', 'success')
      await qc.invalidateQueries({ queryKey: ['manage-cards'] })
    },
    onError: (e: Error) => toast.push(e instanceof ApiError ? e.message : e.message, 'error'),
  })

  const data = listQuery.data
  const cards = data?.cards ?? []
  const pages = data ? Math.max(1, Math.ceil(data.totalCount / data.pageSize)) : 1
  const hasSearch = Boolean(searchInput.trim())

  return (
    <div className="flex h-full min-h-0 min-w-0 max-w-full flex-col gap-3">
      <div className="flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold text-slate-900">
            Miniwebsite Details
          </h1>
          <p className="mt-1 text-sm text-slate-500">{data?.totalCount ?? '—'} websites</p>
        </div>

        <div className="flex w-full max-w-3xl flex-1 flex-wrap items-center justify-end gap-2">
          <Select
            className="w-[180px]"
            value={paymentDraft}
            onChange={(e) => {
              const v = e.target.value
              setPaymentDraft(v)
              setFilters((f) => ({ ...f, paymentFilter: v, page: 1 }))
            }}
          >
            <option value="all">All payments</option>
            <option value="paid">Payment Done</option>
            <option value="unpaid">Payment Not Done</option>
            <option value="trial">Trial Cards</option>
          </Select>

          <div className="relative min-w-[220px] flex-1">
            <Search size={16} className="pointer-events-none absolute top-2.5 left-3 text-slate-400" />
            <Input
              className="pr-9 pl-9"
              placeholder="Search ID / company / email…"
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
                  'MW ID',
                  'User Email',
                  'User Name',
                  'Phone',
                  'Referral',
                  'Company',
                  'Created',
                  'Validity',
                  'MW Status',
                  'View / Edit / Share',
                  'Payment',
                  'Order ₹',
                  'Invoice',
                  'Complimentary',
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
                  <td colSpan={15} className="px-4 py-10 text-center text-slate-500">
                    Loading websites…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td colSpan={15} className="px-4 py-10 text-center text-rose-600">
                    {(listQuery.error as Error).message}
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && !listQuery.isError && cards.length === 0 && (
                <tr>
                  <td colSpan={15} className="px-4 py-10 text-center text-slate-500">
                    No websites found
                  </td>
                </tr>
              )}
              {cards.map((c) => (
                <CardRow
                  key={c.id}
                  card={c}
                  onComplimentary={(yes) => {
                    if (!window.confirm(`${yes ? 'Enable' : 'Disable'} complimentary for MW #${c.id}?`)) return
                    complimentaryMut.mutate({ id: c.id, status: yes ? 'Yes' : 'No' })
                  }}
                />
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex shrink-0 items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
          <div className="text-slate-500">
            Page {data?.page ?? 1} of {pages} · {data?.totalCount ?? 0} websites
          </div>
          <div className="flex gap-2">
            <Button
              variant="secondary"
              disabled={(filters.page ?? 1) <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: Math.max(1, f.page - 1) }))}
            >
              Prev
            </Button>
            <Button
              variant="secondary"
              disabled={(filters.page ?? 1) >= pages}
              onClick={() => setFilters((f) => ({ ...f, page: f.page + 1 }))}
            >
              Next
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}

function CardRow({
  card: c,
  onComplimentary,
}: {
  card: ManageCardRow
  onComplimentary: (yes: boolean) => void
}) {
  return (
    <tr className="border-t border-slate-100 align-middle hover:bg-rose-50/30">
      <td className="px-3 py-3">{c.userId ?? '-'}</td>
      <td className="px-3 py-3 font-medium">{c.id}</td>
      <td className="px-3 py-3 max-w-[180px] truncate">{c.userEmail || '-'}</td>
      <td className="px-3 py-3">{c.userName || '-'}</td>
      <td className="px-3 py-3">{c.userPhone || '-'}</td>
      <td className="px-3 py-3">{c.referralSourceDisplay}</td>
      <td className="px-3 py-3 max-w-[160px] truncate">{c.companyName || '-'}</td>
      <td className="px-3 py-3 whitespace-nowrap text-slate-500">{formatDate(c.uploadedDate)}</td>
      <td className="px-3 py-3 whitespace-nowrap">
        <span className={c.validityTone === 'danger' ? 'text-rose-600 font-medium' : undefined}>
          {c.validityDisplay}
        </span>
      </td>
      <td className="px-3 py-3">
        <Badge tone={tone(c.statusTone)}>{c.statusText}</Badge>
      </td>
      <td className="px-3 py-3">
        <div className="flex items-center gap-1">
          <a
            href={c.publicUrl}
            target="_blank"
            rel="noreferrer"
            className="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100"
            title="View"
          >
            <Eye size={16} />
          </a>
          <a
            href={c.editUrl}
            target="_blank"
            rel="noreferrer"
            className="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100"
            title="Edit (PHP)"
          >
            <Pencil size={16} />
          </a>
          <button
            type="button"
            className="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100"
            title="Share WhatsApp"
            onClick={() => shareCard(c.publicUrl)}
          >
            <Share2 size={16} />
          </button>
          <a
            href={c.publicUrl}
            target="_blank"
            rel="noreferrer"
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"
            title="Open"
          >
            <ExternalLink size={14} />
          </a>
        </div>
      </td>
      <td className="px-3 py-3">
        <Badge tone={c.paymentLabel.startsWith('Paid') ? 'ok' : 'neutral'}>{c.paymentLabel}</Badge>
      </td>
      <td className="px-3 py-3">{c.orderAmount ? `₹${c.orderAmount}` : '-'}</td>
      <td className="px-3 py-3">
        {c.hasInvoice ? (
          <button
            type="button"
            className="inline-flex items-center gap-1 text-rose-600 hover:underline"
            title="Download invoice"
            onClick={() => openInvoiceByCardId(c.id)}
          >
            <Download size={16} />
          </button>
        ) : (
          <span className="text-slate-300">-</span>
        )}
      </td>
      <td className="px-3 py-3 text-center">
        <Toggle
          checked={(c.complimentaryEnabled || 'No') === 'Yes'}
          disabled={!c.canToggleComplimentary || complimentaryBusy(c)}
          onChange={(next) => {
            if (!c.canToggleComplimentary) return
            onComplimentary(next)
          }}
        />
      </td>
    </tr>
  )
}

function complimentaryBusy(_c: ManageCardRow) {
  return false
}