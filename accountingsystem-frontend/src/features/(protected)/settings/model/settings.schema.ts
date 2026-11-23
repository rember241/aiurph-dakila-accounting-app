import { z } from 'zod'

import { zUuid } from '@/lib/schemas/uuid'
import { isValidTimeZone } from '@/features/(protected)/users/tenants/model/tenants.schema'
import { SUPPORTED_CURRENCY_CODES } from '@/utils/currencyFormatter'

const userIdSchema = z.union([z.number().int().positive(), z.string().regex(/^\d+$/)]).transform(value => Number(value))

const timestampSchema = z.string().nullable()
const nullableUuidSchema = zUuid().nullable()

const invoiceColorSchema = z
  .string()
  .trim()
  .regex(/^#[0-9A-Fa-f]{6}$/, 'Use a valid hex color.')

const documentLogoPositionSchema = z.enum(['left', 'center', 'right'])
const documentHeaderAlignmentSchema = z.enum(['left', 'center', 'right'])
const documentHeaderStyleSchema = z.enum(['compact', 'standard', 'formal'])
const documentFooterAlignmentSchema = z.enum(['left', 'center', 'right'])

/**
 * Shared print-layout preferences used by invoice and document previews.
 */
export const documentPrintingSettingsSchema = z.object({
  show_company_logo: z.boolean(),
  logo_position: documentLogoPositionSchema,
  show_company_name: z.boolean(),
  show_company_address: z.boolean(),
  show_company_contact: z.boolean(),
  show_document_title: z.boolean(),
  primary_color: invoiceColorSchema.nullable(),
  accent_color: invoiceColorSchema.nullable(),
  header_alignment: documentHeaderAlignmentSchema,
  header_style: documentHeaderStyleSchema,
  footer_text: z.string().nullable(),
  footer_alignment: documentFooterAlignmentSchema,
  show_page_numbers: z.boolean(),
  footer_note: z.string().nullable()
})

const userCompanySummarySchema = z.object({
  id: zUuid(),
  name: z.string().min(1),
  display_name: z.string().nullable().optional(),
  slug: z.string().min(1),
  timezone: z.string().min(1)
})

const orgUnitSummarySchema = z.object({
  id: zUuid(),
  name: z.string().min(1),
  type: z.string().min(1)
})

const companyOwnerSchema = z.object({
  id: userIdSchema,
  name: z.string().min(1),
  email: z.email()
})

const companyDefaultAccountSummarySchema = z.object({
  id: zUuid(),
  code: z.string().min(1),
  name: z.string().min(1),
  type: z.enum(['asset', 'liability', 'equity', 'revenue', 'expense'])
})

/**
 * API record returned for personal profile settings.
 */
export const userSettingsRecordSchema = z.object({
  id: userIdSchema,
  name: z.string().min(1),
  email: z.email(),
  phone: z.string().nullable(),
  currency_code: z.enum(SUPPORTED_CURRENCY_CODES),
  avatar_path: z.string().nullable().optional(),
  avatar_url: z.string().nullable(),
  avatar_mime: z.string().nullable().optional(),
  avatar_size: z.number().int().nonnegative().nullable().optional(),
  avatar_width: z.number().int().positive().nullable().optional(),
  avatar_height: z.number().int().positive().nullable().optional(),
  avatar_updated_at: timestampSchema.optional(),
  company: userCompanySummarySchema.nullable(),
  primary_org_unit: orgUnitSummarySchema.nullable(),
  created_at: timestampSchema,
  updated_at: timestampSchema
})

/**
 * API record returned for company settings and document defaults.
 */
export const companySettingsRecordSchema = z.object({
  id: zUuid(),
  name: z.string().min(1),
  display_name: z.string().nullable(),
  slug: z.string().min(1),
  email: z.string().email().nullable(),
  phone: z.string().nullable(),
  address: z.string().nullable(),
  logo_path: z.string().nullable(),
  logo_url: z.string().nullable(),
  logo_mime: z.string().nullable(),
  logo_size: z.number().int().nonnegative().nullable(),
  logo_width: z.number().int().positive().nullable(),
  logo_height: z.number().int().positive().nullable(),
  logo_updated_at: timestampSchema,
  invoice_header_title: z.string().nullable(),
  invoice_footer_note: z.string().nullable(),
  invoice_payment_note: z.string().nullable(),
  invoice_primary_color: invoiceColorSchema.nullable(),
  document_show_company_logo: z.boolean(),
  document_logo_position: documentLogoPositionSchema,
  document_show_company_name: z.boolean(),
  document_show_company_address: z.boolean(),
  document_show_company_contact: z.boolean(),
  document_show_document_title: z.boolean(),
  document_accent_color: invoiceColorSchema.nullable(),
  document_header_alignment: documentHeaderAlignmentSchema,
  document_header_style: documentHeaderStyleSchema,
  document_footer_text: z.string().nullable(),
  document_footer_alignment: documentFooterAlignmentSchema,
  document_show_page_numbers: z.boolean().optional().default(true),
  document_printing: documentPrintingSettingsSchema.optional(),
  timezone: z.string().min(1),
  default_accounts_receivable_account_id: nullableUuidSchema,
  default_sales_income_account_id: nullableUuidSchema,
  default_inventory_asset_account_id: nullableUuidSchema,
  default_cogs_account_id: nullableUuidSchema,
  default_sales_tax_payable_account_id: nullableUuidSchema,
  default_accounts_receivable_account: companyDefaultAccountSummarySchema.nullable().optional(),
  default_sales_income_account: companyDefaultAccountSummarySchema.nullable().optional(),
  default_inventory_asset_account: companyDefaultAccountSummarySchema.nullable().optional(),
  default_cogs_account: companyDefaultAccountSummarySchema.nullable().optional(),
  default_sales_tax_payable_account: companyDefaultAccountSummarySchema.nullable().optional(),
  status: z.string().min(1),
  owner: companyOwnerSchema.nullable(),
  created_at: timestampSchema,
  updated_at: timestampSchema
})

export const userSettingsResponseSchema = z.object({
  data: userSettingsRecordSchema,
  message: z.string().optional()
})

export const companySettingsResponseSchema = z.object({
  data: companySettingsRecordSchema,
  message: z.string().optional()
})

/**
 * Generic Laravel validation envelope shared by both settings endpoints.
 */
export const settingsApiErrorSchema = z.object({
  message: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string()).optional()).nullable().optional()
})

/**
 * Form schema for personal settings.
 */
export const userSettingsFormSchema = z.object({
  name: z.string().trim().min(2, 'Full name is required.').max(120, 'Use 120 characters or fewer.'),
  email: z.string().trim().min(1, 'Email is required.').email('Enter a valid email address.'),
  phone: z
    .string()
    .trim()
    .max(40, 'Use 40 characters or fewer.')
    .refine(value => value === '' || /^[0-9+()\-\.\s]+$/.test(value), 'Enter a valid phone number.'),
  currencyCode: z.enum(SUPPORTED_CURRENCY_CODES)
})

/**
 * Form schema for company settings.
 *
 * This covers profile fields, document presentation, and accounting defaults
 * because the current settings UI saves them in one request.
 */
export const companySettingsFormSchema = z.object({
  name: z.string().trim().min(2, 'Company name is required.').max(160, 'Use 160 characters or fewer.'),
  displayName: z.string().trim().max(160, 'Use 160 characters or fewer.'),
  email: z
    .string()
    .trim()
    .max(120, 'Use 120 characters or fewer.')
    .refine(value => value === '' || z.email().safeParse(value).success, 'Enter a valid company email.'),
  phone: z
    .string()
    .trim()
    .max(40, 'Use 40 characters or fewer.')
    .refine(value => value === '' || /^[0-9+()\-\.\s]+$/.test(value), 'Enter a valid company phone number.'),
  address: z.string().trim().max(500, 'Use 500 characters or fewer.'),
  invoiceHeaderTitle: z.string().trim().max(160, 'Use 160 characters or fewer.'),
  invoiceFooterNote: z.string().trim().max(2000, 'Use 2,000 characters or fewer.'),
  invoicePaymentNote: z.string().trim().max(2000, 'Use 2,000 characters or fewer.'),
  invoicePrimaryColor: invoiceColorSchema.or(z.literal('')),
  documentShowCompanyLogo: z.boolean(),
  documentLogoPosition: documentLogoPositionSchema,
  documentShowCompanyName: z.boolean(),
  documentShowCompanyAddress: z.boolean(),
  documentShowCompanyContact: z.boolean(),
  documentShowDocumentTitle: z.boolean(),
  documentAccentColor: invoiceColorSchema.or(z.literal('')),
  documentHeaderAlignment: documentHeaderAlignmentSchema,
  documentHeaderStyle: documentHeaderStyleSchema,
  documentFooterText: z.string().trim().max(500, 'Use 500 characters or fewer.'),
  documentFooterAlignment: documentFooterAlignmentSchema,
  documentShowPageNumbers: z.boolean(),
  timezone: z
    .string()
    .trim()
    .min(1, 'Timezone is required.')
    .refine(value => isValidTimeZone(value), 'Select a valid timezone.'),
  defaultAccountsReceivableAccountId: nullableUuidSchema,
  defaultSalesIncomeAccountId: nullableUuidSchema,
  defaultInventoryAssetAccountId: nullableUuidSchema,
  defaultCogsAccountId: nullableUuidSchema,

  // Optional so that pages which don't render this field as a registered
  // Controller do not fail Zod validation when the field is undefined in
  // RHF's internal _formValues.  The transform below coerces undefined → null
  // so the payload schema always receives a concrete string | null value.
  defaultSalesTaxPayableAccountId: nullableUuidSchema.optional()
})

/**
 * Backend payload schema for personal settings after field normalization.
 */
export const userSettingsPayloadSchema = z.object({
  name: z.string().trim().min(2).max(120),
  email: z.string().trim().min(1).email(),
  phone: z
    .string()
    .trim()
    .max(40)
    .refine(value => value === '' || /^[0-9+()\-\.\s]+$/.test(value))
    .nullable(),
  currency_code: z.enum(SUPPORTED_CURRENCY_CODES)
})

/**
 * Backend payload schema for company settings after camelCase fields are
 * translated into the API contract.
 */
export const companySettingsPayloadSchema = z.object({
  name: z.string().trim().min(2).max(160),
  display_name: z.string().trim().max(160).nullable(),
  email: z
    .string()
    .trim()
    .max(120)
    .refine(value => z.email().safeParse(value).success)
    .nullable(),
  phone: z
    .string()
    .trim()
    .max(40)
    .refine(value => /^[0-9+()\-\.\s]+$/.test(value))
    .nullable(),
  address: z.string().trim().max(500).nullable(),
  invoice_header_title: z.string().trim().max(160).nullable(),
  invoice_footer_note: z.string().trim().max(2000).nullable(),
  invoice_payment_note: z.string().trim().max(2000).nullable(),
  invoice_primary_color: invoiceColorSchema.nullable(),
  document_show_company_logo: z.boolean(),
  document_logo_position: documentLogoPositionSchema,
  document_show_company_name: z.boolean(),
  document_show_company_address: z.boolean(),
  document_show_company_contact: z.boolean(),
  document_show_document_title: z.boolean(),
  document_accent_color: invoiceColorSchema.nullable(),
  document_header_alignment: documentHeaderAlignmentSchema,
  document_header_style: documentHeaderStyleSchema,
  document_footer_text: z.string().trim().max(500).nullable(),
  document_footer_alignment: documentFooterAlignmentSchema,
  document_show_page_numbers: z.boolean(),
  timezone: z
    .string()
    .trim()
    .min(1)
    .refine(value => isValidTimeZone(value)),
  default_accounts_receivable_account_id: nullableUuidSchema,
  default_sales_income_account_id: nullableUuidSchema,
  default_inventory_asset_account_id: nullableUuidSchema,
  default_cogs_account_id: nullableUuidSchema,
  default_sales_tax_payable_account_id: nullableUuidSchema
})

export const userSettingsInputSchema = userSettingsFormSchema.transform(values =>
  userSettingsPayloadSchema.parse({
    name: values.name.trim(),
    email: values.email.trim().toLowerCase(),
    phone: values.phone.trim() || null,
    currency_code: values.currencyCode
  })
)

export const companySettingsInputSchema = companySettingsFormSchema.transform(values =>
  companySettingsPayloadSchema.parse({
    name: values.name.trim(),
    display_name: values.displayName.trim() || null,
    email: values.email.trim().toLowerCase() || null,
    phone: values.phone.trim() || null,
    address: values.address.trim() || null,
    invoice_header_title: values.invoiceHeaderTitle.trim() || null,
    invoice_footer_note: values.invoiceFooterNote.trim() || null,
    invoice_payment_note: values.invoicePaymentNote.trim() || null,
    invoice_primary_color: values.invoicePrimaryColor.trim().toLowerCase() || null,
    document_show_company_logo: values.documentShowCompanyLogo,
    document_logo_position: values.documentLogoPosition,
    document_show_company_name: values.documentShowCompanyName,
    document_show_company_address: values.documentShowCompanyAddress,
    document_show_company_contact: values.documentShowCompanyContact,
    document_show_document_title: values.documentShowDocumentTitle,
    document_accent_color: values.documentAccentColor.trim().toLowerCase() || null,
    document_header_alignment: values.documentHeaderAlignment,
    document_header_style: values.documentHeaderStyle,
    document_footer_text: values.documentFooterText.trim() || null,
    document_footer_alignment: values.documentFooterAlignment,
    document_show_page_numbers: values.documentShowPageNumbers,
    timezone: values.timezone.trim(),
    default_accounts_receivable_account_id: values.defaultAccountsReceivableAccountId,
    default_sales_income_account_id: values.defaultSalesIncomeAccountId,
    default_inventory_asset_account_id: values.defaultInventoryAssetAccountId,
    default_cogs_account_id: values.defaultCogsAccountId,
    default_sales_tax_payable_account_id: values.defaultSalesTaxPayableAccountId ?? null
  })
)

export type UserSettingsRecord = z.output<typeof userSettingsRecordSchema>
export type CompanySettingsRecord = z.output<typeof companySettingsRecordSchema>
export type UserSettingsFormValues = z.input<typeof userSettingsFormSchema>
export type CompanySettingsFormValues = z.input<typeof companySettingsFormSchema>
export type SettingsApiFieldErrors = Record<string, string[] | undefined> | null
export type DocumentPrintingSettings = z.output<typeof documentPrintingSettingsSchema>
