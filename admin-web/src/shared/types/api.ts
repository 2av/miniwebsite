export type ApiResult<T = unknown> = {
  success: boolean
  message?: string | null
  data?: T | null
  errors?: Record<string, string[]> | null
}

export type ManageUsersQuery = {
  page?: number
  pageSize?: number
  search?: string
  statusFilter?: string
  dealFilter?: string
  websiteFilter?: string
  dateFilter?: string
}

export type ManageUsersStats = {
  totalUsers: number
  activeUsers: number
  usersWithDeals: number
  totalWebsites: number
}

export type DealOption = {
  id: number
  dealName: string
  couponCode?: string | null
  planType: string
}

export type MappedDeal = {
  mappingId: number
  dealId: number
  dealName: string
  couponCode?: string | null
}

export type ManageUserRow = {
  id: number
  email: string
  name: string
  phone?: string | null
  state?: string | null
  status: string
  createdAt: string
  collaborationEnabled: string
  saleskitEnabled: string
  refundStatus?: string | null
  refundStatusDate?: string | null
  referredBy?: string | null
  referralSourceDisplay: string
  websiteCount: number
  pendingReferralAmount: number
  totalReferralAmount: number
  totalPaidAmount: number
  lastPaymentDate?: string | null
  mwPaymentStatusLabel: string
  mwDeal?: MappedDeal | null
  franchiseDeal?: MappedDeal | null
}

export type ManageUsersPage = {
  stats: ManageUsersStats
  mwDealCount: number
  franchiseDealCount: number
  mwDeals: DealOption[]
  franchiseDeals: DealOption[]
  users: ManageUserRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type DashboardWebsite = {
  id: number
  companyName?: string | null
  cardId?: string | null
  cardStatus?: string | null
  paymentStatus?: string | null
  paymentDate?: string | null
  uploadedDate?: string | null
  validityDate?: string | null
  complimentaryEnabled?: string | null
  fUserEmail?: string | null
  statusClass: string
  statusText: string
  validityDisplay: string
  paymentLabel: string
}

export type DashboardDetails = {
  userEmail: string
  websites: DashboardWebsite[]
}

export type BankDetails = {
  accountHolderName?: string | null
  accountNumber?: string | null
  ifscCode?: string | null
  bankName?: string | null
  upiId?: string | null
  upiName?: string | null
}

export type ReferredUser = {
  referralId: number
  referredEmail: string
  referralDate: string
  amount: number
  isCollaboration?: string | null
  customerId?: number | null
  franchiseeId?: number | null
  userName?: string | null
  userContact?: string | null
  cardId?: number | null
  cardUploadedDate?: string | null
  cardValidityDate?: string | null
  complimentaryEnabled?: string | null
  paymentStatus?: string | null
  paymentDate?: string | null
  fUserEmail?: string | null
  mwStatusText: string
  validityDisplay: string
}

export type ReferralDetails = {
  referrerEmail: string
  userName: string
  collaborationEnabled: string
  saleskitEnabled: string
  totalReferralAmount: number
  totalPaidAmount: number
  pendingAmount: number
  regularReferrals: number
  collaborationReferrals: number
  bank: BankDetails
  referredUsers: ReferredUser[]
}

export type ManageCardRow = {
  id: number
  cardId?: string | null
  userEmail?: string | null
  fUserEmail?: string | null
  userId?: number | null
  userName?: string | null
  userPhone?: string | null
  referralSourceDisplay: string
  companyName: string
  uploadedDate?: string | null
  validityDate?: string | null
  paymentDate?: string | null
  paymentStatus?: string | null
  complimentaryEnabled?: string | null
  statusText: string
  statusTone: string
  isTrial: boolean
  validityDisplay: string
  validityTone: string
  paymentLabel: string
  orderAmount?: string | null
  hasInvoice: boolean
  canToggleComplimentary: boolean
  publicUrl: string
  editUrl: string
}

export type ManageCardsPage = {
  cards: ManageCardRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type FranchiseDistributorRow = {
  id: number
  email: string
  name: string
  phone?: string | null
  createdAt: string
  referralSourceDisplay: string
  companyName: string
  collaborationEnabled: string
  influencer: string
  frdStatusLabel: string
  cardPaymentStatus: string
  frdFeeDisplay: string
  franchiseInvoiceId?: number | null
  joiningDealInvoiceId?: number | null
  websiteCount: number
  pendingReferralAmount: number
  mwDeal?: MappedDeal | null
  franchiseDeal?: MappedDeal | null
}

export type FranchiseDistributorPage = {
  users: FranchiseDistributorRow[]
  mwDeals: DealOption[]
  franchiseDeals: DealOption[]
  totalCount: number
  page: number
  pageSize: number
}

export type AllOrderRow = {
  invoiceId: number
  userIdDisplay: string
  mwIdDisplay?: string | null
  paymentStatusLabel: string
  paymentStatusTone: string
  paidOnDisplay?: string | null
  totalAmount: number
  totalAmountDisplay: string
}

export type AllOrdersPage = {
  orders: AllOrderRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type FranchiseeRow = {
  id: number
  email: string
  name: string
  phone?: string | null
  createdAt?: string | null
  referralSourceDisplay: string
  companyName: string
  status: string
  isActive: boolean
  firstCardId?: number | null
  firstCardUserEmail?: string | null
  publicUrl: string
  editUrl: string
  paymentStatusLabel: string
  paymentStatusTone: string
  paidOnDisplay?: string | null
  franchiseFee: number
  franchiseFeeDisplay: string
  franchiseInvoiceId?: number | null
  websiteCount: number
  documentStatus: string
  documentStatusTone: string
  walletBalance: number
  walletBalanceDisplay: string
}

export type FranchiseePage = {
  franchisees: FranchiseeRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type FranchiseeWebsite = {
  id: number
  companyName?: string | null
  uploadedDate?: string | null
  validityDate?: string | null
  statusText: string
  paymentLabel: string
  publicUrl: string
}

export type FranchiseeDashboard = {
  email: string
  websites: FranchiseeWebsite[]
}

export type UserDeletionRow = {
  id: number
  email: string
  name: string
  role: string
  status: string
  isDeleted: boolean
  createdAt: string
  updatedAt?: string | null
}

export type UserDeletionPage = {
  users: UserDeletionRow[]
  totalCount: number
  activeCount: number
  deletedCount: number
  page: number
  pageSize: number
}

export type ManageReferralRow = {
  referralId: number
  referrerEmail: string
  userIdDisplay: string
  userName: string
  userPhone: string
  referredToDisplay: string
  referralAmountDisplay: string
  referralAmount: number
  refundStatus: string
  mwPaymentStatusLabel: string
  mwPaymentStatusTone: string
  latestCardId?: number | null
  hasInvoice: boolean
}

export type ManageReferralsPage = {
  referrals: ManageReferralRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type ReferrerPaymentLine = {
  referralId: number
  referredEmail: string
  referredName: string
  referralDate: string
  userPaymentStatusLabel: string
  userPaymentStatusTone: string
  totalAmount: number
  paidAmount: number
  pendingAmount: number
  statusLabel: string
  statusTone: string
  canAddPayment: boolean
  hasHistory: boolean
}

export type ReferrerPaymentDetails = {
  referrerEmail: string
  referrerName: string
  lines: ReferrerPaymentLine[]
}

export type ReferralPaymentHistoryItem = {
  paymentDate: string
  amount: number
  transactionNumber: string
  paymentMethod: string
  paymentNotes?: string | null
  processedBy: string
}

export type ReferralPaymentHistory = {
  referralId: number
  referrerName: string
  referredName: string
  referralAmount: number
  items: ReferralPaymentHistoryItem[]
}

export type ManageReferralBankDetails = {
  userEmail: string
  accountHolderName?: string | null
  accountNumber?: string | null
  ifscCode?: string | null
  bankName?: string | null
  upiId?: string | null
  upiName?: string | null
}

export type ManageDealRow = {
  id: number
  planName: string
  planType: string
  dealState?: string | null
  dealStateDisplay: string
  dealName: string
  couponCode: string
  createdAt?: string | null
  createdAtDisplay: string
  bonusAmount: number
  bonusAmountDisplay: string
  discountAmount: number
  discountPercentage: number
  discountDisplay: string
  validityDate: string
  validityDateDisplay: string
  isExpired: boolean
  maxUsage: number
  currentUsage: number
  usageDisplay: string
  dealStatus: string
  statusTone: string
}

export type ManageDealsPage = {
  deals: ManageDealRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type ManageDealsMeta = {
  states: string[]
}

export type UpsertDealPayload = {
  planName: string
  planType: string
  dealName: string
  couponCode: string
  bonusAmount: number
  discountAmount: number
  discountPercentage: number
  validityDate: string
  maxUsage: number
  dealState?: string
  createdBy?: string
}

export type FranchiseeWalletLookup = {
  userId: number
  email: string
  name: string
  phone?: string | null
  currentBalance: number
  currentBalanceDisplay: string
}

export type WalletRechargeResult = {
  userEmail: string
  userName: string
  amountCredited: number
  newBalance: number
  amountDisplay: string
  newBalanceDisplay: string
  message: string
}

export type ManageFaqRow = {
  id: number
  pageType: string
  pageTypeDisplay: string
  question: string
  answer: string
  answerPreview: string
  sortOrder: number
  status: string
  statusTone: string
}

export type ManageFaqsPage = {
  faqs: ManageFaqRow[]
  totalCount: number
  page: number
  pageSize: number
}

export type FaqPageTypeOption = {
  value: string
  label: string
}

export type ManageFaqsMeta = {
  pageTypes: FaqPageTypeOption[]
}

export type UpsertFaqPayload = {
  pageType: string
  question: string
  answer: string
  sortOrder: number
  status?: string
}

export type ContentTypeOption = {
  value: string
  label: string
  badge: string
}

export type ManageContentMeta = {
  contentTypes: ContentTypeOption[]
}

export type ManageContentItem = {
  contentType: string
  title: string
  content: string
  metaDescription: string
  metaKeywords: string
  lastUpdated?: string | null
  lastUpdatedDisplay?: string | null
  updatedBy?: string | null
}

export type ManageContentList = {
  items: ManageContentItem[]
}

export type UpsertContentPayload = {
  contentType: string
  title: string
  content: string
  metaDescription?: string
  metaKeywords?: string
  updatedBy?: string
}
