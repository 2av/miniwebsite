import { useQuery } from '@tanstack/react-query'
import { Download, RefreshCw, Search, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { fetchAllOrders } from '@/features/all-orders/api'
import type { AllOrderRow } from '@/shared/types/api'
import { openInvoiceById } from '@/shared/lib/invoiceDownload'
import { Badge, Button, Card, Input } from '@/shared/ui/primitives'

function tone(value: string): 'neutral' | 'ok' | 'warn' | 'danger' {
  if (value === 'ok') return 'ok'
  if (value === 'warn') return 'warn'
  if (value === 'danger') return 'danger'
  return 'neutral'
}

export function AllOrdersPage() {
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

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
  const queryKey = useMemo(() => ['all-orders', filters] as const, [filters])

  const listQuery = useQuery({
    queryKey,
    queryFn: () => fetchAllOrders(filters),
  })

  const data = listQuery.data
  const orders = data?.orders ?? []
  const pages = data ? Math.max(1, Math.ceil(data.totalCount / data.pageSize)) : 1
  const hasSearch = Boolean(searchInput.trim())

  return (
    <div className="flex h-full min-h-0 min-w-0 max-w-full flex-col gap-3">
      <div className="flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold text-slate-900">
            All Orders
          </h1>
          <p className="mt-1 text-sm text-slate-500">{data?.totalCount ?? '—'} invoices</p>
        </div>
        <div className="flex w-full max-w-xl flex-1 flex-wrap items-center justify-end gap-2">
          <div className="relative min-w-[220px] flex-1">
            <Search size={16} className="pointer-events-none absolute top-2.5 left-3 text-slate-400" />
            <Input
              className="pr-9 pl-9"
              placeholder="Search email / name / invoice no…"
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
          <table className="w-full min-w-[800px] text-left text-sm">
            <thead className="sticky top-0 z-10 bg-slate-900 text-xs tracking-wide text-slate-200 uppercase">
              <tr>
                {['USER ID / FR ID', 'MW ID', 'Payment Status', 'Payment Date', 'Total Order Value', 'Invoice'].map(
                  (h) => (
                    <th key={h} className="px-4 py-3 font-semibold whitespace-nowrap">
                      {h}
                    </th>
                  ),
                )}
              </tr>
            </thead>
            <tbody>
              {listQuery.isLoading && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-slate-500">
                    Loading…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-rose-600">
                    {(listQuery.error as Error).message}
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && !listQuery.isError && orders.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-slate-500">
                    No invoices found
                  </td>
                </tr>
              )}
              {orders.map((o) => (
                <OrderRow key={o.invoiceId} order={o} />
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex shrink-0 items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
          <div className="text-slate-500">
            Page {data?.page ?? 1} of {pages} · {data?.totalCount ?? 0} orders · 10 per page
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={page <= 1 || listQuery.isFetching} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              Prev
            </Button>
            <Button variant="secondary" disabled={page >= pages || listQuery.isFetching} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}

function OrderRow({ order: o }: { order: AllOrderRow }) {
  return (
    <tr className="border-t border-slate-100 hover:bg-rose-50/30">
      <td className="px-4 py-3 font-medium whitespace-nowrap">{o.userIdDisplay}</td>
      <td className="px-4 py-3 whitespace-nowrap">{o.mwIdDisplay || '-'}</td>
      <td className="px-4 py-3">
        <Badge tone={tone(o.paymentStatusTone)}>{o.paymentStatusLabel}</Badge>
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-slate-600">{o.paidOnDisplay || '-'}</td>
      <td className="px-4 py-3 font-semibold whitespace-nowrap">{o.totalAmountDisplay}</td>
      <td className="px-4 py-3">
        <button
          type="button"
          className="inline-flex text-rose-600 hover:underline"
          title="Download invoice"
          onClick={() => openInvoiceById(o.invoiceId)}
        >
          <Download size={16} />
        </button>
      </td>
    </tr>
  )
}
