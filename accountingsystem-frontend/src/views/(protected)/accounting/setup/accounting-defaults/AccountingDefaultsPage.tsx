'use client'

import { type HTMLAttributes, useEffect, useMemo } from 'react'

import { z } from 'zod'
import { Controller, useForm } from 'react-hook-form'

import { zodResolver } from '@hookform/resolvers/zod'

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { Bounce, toast, ToastContainer } from 'react-toastify'

import { zUuid } from '@/lib/schemas/uuid'

import CustomPageHeader from '@components/app/CustomPageHeader'
import CustomAutocomplete from '@core/components/mui/Autocomplete'
import CustomTextField from '@core/components/mui/TextField'

import { getFriendlySettingsErrorMessage, SettingsApiError } from '@/features/(protected)/settings/api/settings.api'
import {
  useCompanySettingsQuery,
  useUpdateCompanySettingsMutation
} from '@/features/(protected)/settings/api/settings.queries'
import { useAccountsQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'

import {
  compareAccountsByCoreTypeAndCode,
  getAccountCoreTypeLabel,
  type AccountColumn
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import type {
  CompanySettingsFormValues,
  CompanySettingsRecord
} from '@/features/(protected)/settings/model/settings.schema'

// ---------------------------------------------------------------------------
// Domain types
// ---------------------------------------------------------------------------

type AccountOption = Pick<AccountColumn, 'id' | 'code' | 'name' | 'type'>

// ---------------------------------------------------------------------------
// Focused form schema.
//
// This schema intentionally contains only the five posting account IDs.
// All other company fields (name, timezone, address, etc.) are injected from
// the cached settings record at submit time, which eliminates any possibility
// of silent validation failures on fields not rendered in this form.
// ---------------------------------------------------------------------------

const accountingDefaultsFormSchema = z.object({
  arAccountId: zUuid().nullable(),
  salesIncomeAccountId: zUuid().nullable(),
  inventoryAssetAccountId: zUuid().nullable(),
  cogsAccountId: zUuid().nullable(),
  salesTaxPayableAccountId: zUuid().nullable()
})

type AccountingDefaultsFormValues = z.infer<typeof accountingDefaultsFormSchema>

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

function formatAccountLabel(account: AccountOption): string {
  return `${account.code} — ${account.name}`
}

/**
 * Resolves the Autocomplete value from the live options list.
 * Falls back to the pre-loaded account summary from the settings cache so
 * the selected label is visible even before the full options list loads.
 */
function resolveSelectedOption(
  options: AccountOption[],
  accountId: string | null | undefined,
  cached: AccountOption | null | undefined
): AccountOption | null {
  if (!accountId) return null

  return options.find(o => o.id === accountId) ?? cached ?? null
}

/**
 * Merges the accounting-defaults form values with the current company settings
 * record to produce a complete PUT payload.  This is necessary because the
 * company settings endpoint requires all company fields (name, timezone, etc.),
 * none of which are present in this focused form.
 */
function buildCompanySettingsPayload(
  settings: CompanySettingsRecord,
  accounting: AccountingDefaultsFormValues
): CompanySettingsFormValues {
  return {
    name: settings.name,
    displayName: settings.display_name ?? '',
    email: settings.email ?? '',
    phone: settings.phone ?? '',
    address: settings.address ?? '',
    invoiceHeaderTitle: settings.invoice_header_title ?? '',
    invoiceFooterNote: settings.invoice_footer_note ?? '',
    invoicePaymentNote: settings.invoice_payment_note ?? '',
    invoicePrimaryColor: settings.invoice_primary_color ?? '#3949ab',
    documentShowCompanyLogo: settings.document_show_company_logo ?? true,
    documentLogoPosition: settings.document_logo_position ?? 'left',
    documentShowCompanyName: settings.document_show_company_name ?? true,
    documentShowCompanyAddress: settings.document_show_company_address ?? true,
    documentShowCompanyContact: settings.document_show_company_contact ?? true,
    documentShowDocumentTitle: settings.document_show_document_title ?? true,
    documentAccentColor: settings.document_accent_color ?? '',
    documentHeaderAlignment: settings.document_header_alignment ?? 'left',
    documentHeaderStyle: settings.document_header_style ?? 'standard',
    documentFooterText: settings.document_footer_text ?? '',
    documentFooterAlignment: settings.document_footer_alignment ?? 'center',
    documentShowPageNumbers: settings.document_show_page_numbers ?? true,
    timezone: settings.timezone,
    defaultAccountsReceivableAccountId: accounting.arAccountId,
    defaultSalesIncomeAccountId: accounting.salesIncomeAccountId,
    defaultInventoryAssetAccountId: accounting.inventoryAssetAccountId,
    defaultCogsAccountId: accounting.cogsAccountId,
    defaultSalesTaxPayableAccountId: accounting.salesTaxPayableAccountId
  }
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function AccountOptionItem(props: HTMLAttributes<HTMLElement> & { option: AccountOption }) {
  const { option, ...rest } = props

  return (
    <Box component='li' {...rest}>
      <Stack spacing={0.25}>
        <Typography variant='body2' fontWeight={600}>
          {option.code}
        </Typography>
        <Typography variant='caption' color='text.secondary'>
          {option.name}
        </Typography>
      </Stack>
    </Box>
  )
}

// ---------------------------------------------------------------------------
// Page component
// ---------------------------------------------------------------------------

export function AccountingDefaultsPage() {
  const settingsQuery = useCompanySettingsQuery()
  const updateMutation = useUpdateCompanySettingsMutation()

  // -------------------------------------------------------------------------
  // Account lists — each dropdown is filtered to the correct account type.
  // Mixing types would violate double-entry bookkeeping rules.
  // -------------------------------------------------------------------------

  const assetAccountsQuery = useAccountsQuery({
    type: 'asset',
    status: 'active',
    is_posting: true,
    page: 0,
    pageSize: 100,
    sortField: 'code',
    sortDir: 'asc'
  })

  const revenueAccountsQuery = useAccountsQuery({
    type: 'revenue',
    status: 'active',
    is_posting: true,
    page: 0,
    pageSize: 100,
    sortField: 'code',
    sortDir: 'asc'
  })

  const expenseAccountsQuery = useAccountsQuery({
    type: 'expense',
    status: 'active',
    is_posting: true,
    page: 0,
    pageSize: 100,
    sortField: 'code',
    sortDir: 'asc'
  })

  const liabilityAccountsQuery = useAccountsQuery({
    type: 'liability',
    status: 'active',
    is_posting: true,
    page: 0,
    pageSize: 100,
    sortField: 'code',
    sortDir: 'asc'
  })

  const assetOptions = useMemo(
    () => [...(assetAccountsQuery.data?.data ?? [])].sort(compareAccountsByCoreTypeAndCode),
    [assetAccountsQuery.data?.data]
  )

  const revenueOptions = useMemo(
    () => [...(revenueAccountsQuery.data?.data ?? [])].sort(compareAccountsByCoreTypeAndCode),
    [revenueAccountsQuery.data?.data]
  )

  const expenseOptions = useMemo(
    () => [...(expenseAccountsQuery.data?.data ?? [])].sort(compareAccountsByCoreTypeAndCode),
    [expenseAccountsQuery.data?.data]
  )

  const liabilityOptions = useMemo(
    () => [...(liabilityAccountsQuery.data?.data ?? [])].sort(compareAccountsByCoreTypeAndCode),
    [liabilityAccountsQuery.data?.data]
  )

  // -------------------------------------------------------------------------
  // Form — focused schema contains only the five account IDs
  // -------------------------------------------------------------------------

  const {
    control,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isDirty, isSubmitting }
  } = useForm<AccountingDefaultsFormValues>({
    resolver: zodResolver(accountingDefaultsFormSchema),
    defaultValues: {
      arAccountId: null,
      salesIncomeAccountId: null,
      inventoryAssetAccountId: null,
      cogsAccountId: null,
      salesTaxPayableAccountId: null
    }
  })

  // Populate the form once company settings arrive
  useEffect(() => {
    if (!settingsQuery.data) return

    reset({
      arAccountId: settingsQuery.data.default_accounts_receivable_account_id,
      salesIncomeAccountId: settingsQuery.data.default_sales_income_account_id,
      inventoryAssetAccountId: settingsQuery.data.default_inventory_asset_account_id,
      cogsAccountId: settingsQuery.data.default_cogs_account_id,
      salesTaxPayableAccountId: settingsQuery.data.default_sales_tax_payable_account_id
    })
  }, [reset, settingsQuery.data])

  // -------------------------------------------------------------------------
  // Submit handler
  // -------------------------------------------------------------------------

  const onSubmit = handleSubmit(async values => {
    if (!settingsQuery.data) return

    try {
      await toast.promise(updateMutation.mutateAsync(buildCompanySettingsPayload(settingsQuery.data, values)), {
        pending: 'Saving accounting defaults…',
        success: 'Accounting defaults saved.',
        error: {
          render({ data }: { data: unknown }) {
            return getFriendlySettingsErrorMessage(data, { action: 'save', scope: 'company' })
          }
        }
      })
    } catch (error) {
      if (!(error instanceof SettingsApiError)) return

      const fe = error.fieldErrors

      if (fe?.default_accounts_receivable_account_id?.[0]) {
        setError('arAccountId', { type: 'server', message: fe.default_accounts_receivable_account_id[0] })
      }

      if (fe?.default_sales_income_account_id?.[0]) {
        setError('salesIncomeAccountId', { type: 'server', message: fe.default_sales_income_account_id[0] })
      }

      if (fe?.default_inventory_asset_account_id?.[0]) {
        setError('inventoryAssetAccountId', { type: 'server', message: fe.default_inventory_asset_account_id[0] })
      }

      if (fe?.default_cogs_account_id?.[0]) {
        setError('cogsAccountId', { type: 'server', message: fe.default_cogs_account_id[0] })
      }

      if (fe?.default_sales_tax_payable_account_id?.[0]) {
        setError('salesTaxPayableAccountId', { type: 'server', message: fe.default_sales_tax_payable_account_id[0] })
      }
    }
  })

  // -------------------------------------------------------------------------
  // Loading state
  // -------------------------------------------------------------------------

  if (settingsQuery.isLoading) {
    return (
      <Stack alignItems='center' justifyContent='center' sx={{ py: 10 }}>
        <CircularProgress />
      </Stack>
    )
  }

  const isSettingsBusy = settingsQuery.isFetching

  // -------------------------------------------------------------------------
  // Render
  // -------------------------------------------------------------------------

  return (
    <>
      <ToastContainer
        autoClose={2500}
        hideProgressBar={false}
        closeOnClick
        transition={Bounce}
        position='top-center'
        pauseOnFocusLoss={false}
      />

      <CustomPageHeader
        title='Accounting Defaults'
        description='Default posting accounts used when sales invoices generate journal entries automatically.'
      />

      <Paper sx={{ p: 6 }} component='form' onSubmit={onSubmit}>
        {/* ------------------------------------------------------------------ */}
        {/* Section header                                                       */}
        {/* ------------------------------------------------------------------ */}

        <Stack spacing={1} sx={{ mb: 4 }}>
          <Typography variant='h5'>Sales & Inventory Posting Accounts</Typography>
          <Typography variant='body2' color='text.secondary'>
            When a sales invoice is issued, the system automatically posts a journal entry using these accounts. Only
            active posting accounts of the correct type are listed in each dropdown.
          </Typography>
        </Stack>

        {/* ------------------------------------------------------------------ */}
        {/* Journal entry legend — helps accountants confirm the mapping         */}
        {/* ------------------------------------------------------------------ */}

        <Alert severity='info' sx={{ mb: 5, fontFamily: 'monospace' }}>
          <Typography variant='body2' fontWeight={600} sx={{ mb: 0.5 }}>
            Journal entry generated per issued invoice:
          </Typography>
          <Box component='pre' sx={{ m: 0, fontSize: '0.78rem', lineHeight: 1.7 }}>
            {[
              'Dr  Accounts Receivable       ← customer balance due',
              '  Cr  Sales Revenue             ← income recognized',
              '  Cr  Sales Tax Payable         ← output tax collected (if applicable)',
              'Dr  Cost of Goods Sold         ← cost of inventory sold (if applicable)',
              '  Cr  Inventory Asset           ← inventory relieved (if applicable)'
            ].join('\n')}
          </Box>
        </Alert>

        <Divider sx={{ mb: 5 }} />

        {/* ------------------------------------------------------------------ */}
        {/* Account pickers                                                       */}
        {/* ------------------------------------------------------------------ */}

        <Grid container spacing={6}>
          {/* --- Row 1: Income side ----------------------------------------- */}

          {/* Accounts Receivable — asset account, debited on invoice issue */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Controller
              name='arAccountId'
              control={control}
              render={({ field }) => (
                <CustomAutocomplete<AccountOption, false, false, false>
                  options={assetOptions}
                  value={resolveSelectedOption(
                    assetOptions,
                    field.value,
                    settingsQuery.data?.default_accounts_receivable_account
                  )}
                  onChange={(_, value) => field.onChange(value?.id ?? null)}
                  disabled={isSettingsBusy || isSubmitting}
                  loading={assetAccountsQuery.isLoading}
                  getOptionLabel={formatAccountLabel}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  groupBy={option => getAccountCoreTypeLabel(option.type)}
                  noOptionsText={assetAccountsQuery.isLoading ? 'Loading accounts…' : 'No active asset accounts found'}
                  renderOption={(props, option) => {
                    const { key, ...listProps } = props as HTMLAttributes<HTMLElement> & { key: string }

                    return <AccountOptionItem key={key ?? option.id} {...listProps} option={option} />
                  }}
                  renderInput={params => (
                    <CustomTextField
                      {...params}
                      inputRef={field.ref}
                      label='Accounts Receivable'
                      placeholder='Search asset accounts…'
                      error={!!errors.arAccountId}
                      helperText={
                        errors.arAccountId?.message ?? 'Asset account — debited when a customer invoice is issued.'
                      }
                    />
                  )}
                />
              )}
            />
          </Grid>

          {/* Sales Revenue — revenue account, credited on invoice issue */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Controller
              name='salesIncomeAccountId'
              control={control}
              render={({ field }) => (
                <CustomAutocomplete<AccountOption, false, false, false>
                  options={revenueOptions}
                  value={resolveSelectedOption(
                    revenueOptions,
                    field.value,
                    settingsQuery.data?.default_sales_income_account
                  )}
                  onChange={(_, value) => field.onChange(value?.id ?? null)}
                  disabled={isSettingsBusy || isSubmitting}
                  loading={revenueAccountsQuery.isLoading}
                  getOptionLabel={formatAccountLabel}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  groupBy={option => getAccountCoreTypeLabel(option.type)}
                  noOptionsText={
                    revenueAccountsQuery.isLoading ? 'Loading accounts…' : 'No active revenue accounts found'
                  }
                  renderOption={(props, option) => {
                    const { key, ...listProps } = props as HTMLAttributes<HTMLElement> & { key: string }

                    return <AccountOptionItem key={key ?? option.id} {...listProps} option={option} />
                  }}
                  renderInput={params => (
                    <CustomTextField
                      {...params}
                      inputRef={field.ref}
                      label='Sales Revenue'
                      placeholder='Search revenue accounts…'
                      error={!!errors.salesIncomeAccountId}
                      helperText={
                        errors.salesIncomeAccountId?.message ??
                        'Revenue account — credited when sales income is recognized on a customer invoice.'
                      }
                    />
                  )}
                />
              )}
            />
          </Grid>

          {/* --- Row 2: Inventory cost side ---------------------------------- */}

          {/* Inventory Asset — asset account, credited when inventory is delivered */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Controller
              name='inventoryAssetAccountId'
              control={control}
              render={({ field }) => (
                <CustomAutocomplete<AccountOption, false, false, false>
                  options={assetOptions}
                  value={resolveSelectedOption(
                    assetOptions,
                    field.value,
                    settingsQuery.data?.default_inventory_asset_account
                  )}
                  onChange={(_, value) => field.onChange(value?.id ?? null)}
                  disabled={isSettingsBusy || isSubmitting}
                  loading={assetAccountsQuery.isLoading}
                  getOptionLabel={formatAccountLabel}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  groupBy={option => getAccountCoreTypeLabel(option.type)}
                  noOptionsText={assetAccountsQuery.isLoading ? 'Loading accounts…' : 'No active asset accounts found'}
                  renderOption={(props, option) => {
                    const { key, ...listProps } = props as HTMLAttributes<HTMLElement> & { key: string }

                    return <AccountOptionItem key={key ?? option.id} {...listProps} option={option} />
                  }}
                  renderInput={params => (
                    <CustomTextField
                      {...params}
                      inputRef={field.ref}
                      label='Inventory Asset'
                      placeholder='Search asset accounts…'
                      error={!!errors.inventoryAssetAccountId}
                      helperText={
                        errors.inventoryAssetAccountId?.message ??
                        'Asset account — credited when inventory stock is relieved upon invoice issuance.'
                      }
                    />
                  )}
                />
              )}
            />
          </Grid>

          {/* Cost of Goods Sold — expense account, debited when inventory is delivered */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Controller
              name='cogsAccountId'
              control={control}
              render={({ field }) => (
                <CustomAutocomplete<AccountOption, false, false, false>
                  options={expenseOptions}
                  value={resolveSelectedOption(expenseOptions, field.value, settingsQuery.data?.default_cogs_account)}
                  onChange={(_, value) => field.onChange(value?.id ?? null)}
                  disabled={isSettingsBusy || isSubmitting}
                  loading={expenseAccountsQuery.isLoading}
                  getOptionLabel={formatAccountLabel}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  groupBy={option => getAccountCoreTypeLabel(option.type)}
                  noOptionsText={
                    expenseAccountsQuery.isLoading ? 'Loading accounts…' : 'No active expense accounts found'
                  }
                  renderOption={(props, option) => {
                    const { key, ...listProps } = props as HTMLAttributes<HTMLElement> & { key: string }

                    return <AccountOptionItem key={key ?? option.id} {...listProps} option={option} />
                  }}
                  renderInput={params => (
                    <CustomTextField
                      {...params}
                      inputRef={field.ref}
                      label='Cost of Goods Sold'
                      placeholder='Search expense accounts…'
                      error={!!errors.cogsAccountId}
                      helperText={
                        errors.cogsAccountId?.message ??
                        'Expense account — debited for the cost of inventory items included on each invoice.'
                      }
                    />
                  )}
                />
              )}
            />
          </Grid>

          {/* --- Row 3: Tax -------------------------------------------------- */}

          {/* Sales Tax Payable — liability account, credited on taxed invoices */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Controller
              name='salesTaxPayableAccountId'
              control={control}
              render={({ field }) => (
                <CustomAutocomplete<AccountOption, false, false, false>
                  options={liabilityOptions}
                  value={resolveSelectedOption(
                    liabilityOptions,
                    field.value,
                    settingsQuery.data?.default_sales_tax_payable_account
                  )}
                  onChange={(_, value) => field.onChange(value?.id ?? null)}
                  disabled={isSettingsBusy || isSubmitting}
                  loading={liabilityAccountsQuery.isLoading}
                  getOptionLabel={formatAccountLabel}
                  isOptionEqualToValue={(option, value) => option.id === value.id}
                  groupBy={option => getAccountCoreTypeLabel(option.type)}
                  noOptionsText={
                    liabilityAccountsQuery.isLoading ? 'Loading accounts…' : 'No active liability accounts found'
                  }
                  renderOption={(props, option) => {
                    const { key, ...listProps } = props as HTMLAttributes<HTMLElement> & { key: string }

                    return <AccountOptionItem key={key ?? option.id} {...listProps} option={option} />
                  }}
                  renderInput={params => (
                    <CustomTextField
                      {...params}
                      inputRef={field.ref}
                      label='Sales Tax Payable'
                      placeholder='Search liability accounts…'
                      error={!!errors.salesTaxPayableAccountId}
                      helperText={
                        errors.salesTaxPayableAccountId?.message ??
                        'Liability account — credited for output tax collected on taxable customer invoices.'
                      }
                    />
                  )}
                />
              )}
            />
          </Grid>
        </Grid>

        {/* ------------------------------------------------------------------ */}
        {/* Actions                                                              */}
        {/* ------------------------------------------------------------------ */}

        <Stack direction='row' spacing={3} justifyContent='flex-end' sx={{ mt: 6 }}>
          <Button
            type='button'
            variant='tonal'
            color={isDirty ? 'error' : 'secondary'}
            disabled={isSubmitting}
            onClick={() => reset()}
          >
            {isDirty ? 'Discard Changes' : 'Reset'}
          </Button>
          <Button
            type='submit'
            variant='contained'
            disabled={isSubmitting || !isDirty || !settingsQuery.data}
            loading={isSubmitting}
          >
            Save Defaults
          </Button>
        </Stack>
      </Paper>
    </>
  )
}
