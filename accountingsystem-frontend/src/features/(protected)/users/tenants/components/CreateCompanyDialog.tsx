'use client'

import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'

import { useRouter } from 'next/navigation'

import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import { format, startOfMonth } from 'date-fns'

import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import LinearProgress from '@mui/material/LinearProgress'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomAutocomplete from '@core/components/mui/Autocomplete'
import CustomTextField from '@core/components/mui/TextField'
import AppReactDatepicker from '@/libs/styles/AppReactDatepicker'
import OnboardingWizardStepper from '@views/onboarding/company/components/OnboardingWizardStepper'

import { useMeQuery } from '@/features/auth/api/auth.queries'
import { CompanySetupApiError } from '@/features/(protected)/users/tenants/api/company-setup.api'
import { useCompleteTenantCompanySetupMutation } from '@/features/(protected)/users/tenants/api/company-setup.queries'
import {
  getAccountsSetupModeDescription,
  getAccountsSetupModeLabel,
  STARTER_ACCOUNTS_PREVIEW_COUNT,
  STARTER_ACCOUNTS_PREVIEW_GROUPS
} from '@/features/onboarding/model/company-onboarding-accounts'
import { companyOnboardingFormSchema } from '@/features/onboarding/model/company-onboarding.schema'
import type {
  CompanyOnboardingApiFieldErrors,
  CompanyOnboardingFormValues,
  CompanyOnboardingResponse
} from '@/features/onboarding/model/company-onboarding.types'
import {
  createCompanyWizardSteps,
  findCreateCompanyStepIndexByField
} from '@/features/(protected)/users/tenants/model/create-company-flow'
import {
  buildSlugPreview,
  buildTimezoneOptions,
  resolveLocalTimezone
} from '@/features/(protected)/users/tenants/utils/tenant-form'

import type { TenantRecord } from '@/features/(protected)/users/tenants/model/tenants.types'

type CreateCompanyDialogProps = {
  open: boolean
  onClose: (reason?: 'cancel' | 'completed') => void
  onCreated?: (tenant: TenantRecord) => void
}

type FormStatus = {
  type: 'success' | 'error'
  message: string
} | null

const defaultFiscalYearStartDate = format(startOfMonth(new Date()), 'yyyy-MM-dd')

const FIELD_ERROR_MAP: Record<keyof CompanyOnboardingFormValues, string[]> = {
  companyName: ['companyName', 'company.name'],
  companySlug: ['companySlug', 'company.slug'],
  companyTimezone: ['companyTimezone', 'company.timezone'],
  createRootOrgUnit: ['createRootOrgUnit', 'organization.create_root_org_unit'],
  rootOrgUnitName: ['rootOrgUnitName', 'organization.root_org_unit_name'],
  fiscalYearCode: ['fiscalYearCode', 'fiscal_year.code'],
  fiscalYearName: ['fiscalYearName', 'fiscal_year.name'],
  fiscalYearStartDate: ['fiscalYearStartDate', 'fiscal_year.start_date'],
  fiscalYearEndDate: ['fiscalYearEndDate', 'fiscal_year.end_date'],
  accountsSetupMode: ['accountsSetupMode', 'accounts_setup.mode']
}

const KNOWN_FIELD_ERROR_KEYS = new Set(Object.values(FIELD_ERROR_MAP).flat())

const baseDefaults: CompanyOnboardingFormValues = {
  companyName: '',
  companySlug: '',
  companyTimezone: '',
  createRootOrgUnit: true,
  rootOrgUnitName: 'Head Office',
  fiscalYearCode: `FY${new Date().getFullYear()}`,
  fiscalYearName: `Fiscal Year ${new Date().getFullYear()}`,
  fiscalYearStartDate: defaultFiscalYearStartDate,
  fiscalYearEndDate: buildFiscalYearEndDate(defaultFiscalYearStartDate),
  accountsSetupMode: 'generated'
}

function buildFiscalYearEndDate(startDate: string) {
  const start = startOfMonth(new Date(`${startDate}T00:00:00`))
  const end = new Date(start.getFullYear() + 1, start.getMonth(), 0)

  return format(end, 'yyyy-MM-dd')
}

function formatDisplayDate(value: string) {
  if (!value) return 'Not set'

  const parsedDate = new Date(`${value}T00:00:00`)

  if (Number.isNaN(parsedDate.getTime())) {
    return value
  }

  return format(parsedDate, 'MMMM d, yyyy')
}

function collectKnownFieldMessages(fieldErrors: CompanyOnboardingApiFieldErrors | null) {
  if (!fieldErrors) return []

  return (Object.keys(FIELD_ERROR_MAP) as Array<keyof CompanyOnboardingFormValues>)
    .map(fieldName => FIELD_ERROR_MAP[fieldName].map(key => fieldErrors[key]?.[0]).find(Boolean))
    .filter((message): message is string => Boolean(message))
}

function getGlobalCompanySetupErrorMessage(fieldErrors: CompanyOnboardingApiFieldErrors | null) {
  if (!fieldErrors) return null

  for (const [key, messages] of Object.entries(fieldErrors)) {
    if (KNOWN_FIELD_ERROR_KEYS.has(key)) {
      continue
    }

    const message = messages?.[0]

    if (message) {
      return message
    }
  }

  return null
}

function getCompanySetupErrorSummary(error: CompanySetupApiError) {
  const globalMessage = getGlobalCompanySetupErrorMessage(error.fieldErrors)

  if (globalMessage) {
    return globalMessage
  }

  const fieldMessages = collectKnownFieldMessages(error.fieldErrors)

  if (fieldMessages.length === 1) {
    return fieldMessages[0]
  }

  if (fieldMessages.length > 1) {
    return 'Review the highlighted fields and try again.'
  }

  return error.message || 'Company creation failed.'
}

function CreateCompanyDialog({ open, onClose, onCreated }: CreateCompanyDialogProps) {
  const router = useRouter()
  const meQuery = useMeQuery()
  const companySetupMutation = useCompleteTenantCompanySetupMutation()

  const [detectedTimezone, setDetectedTimezone] = useState('UTC')
  const [formStatus, setFormStatus] = useState<FormStatus>(null)
  const [activeStep, setActiveStep] = useState(0)
  const [createdSetup, setCreatedSetup] = useState<CompanyOnboardingResponse['data'] | null>(null)

  const {
    control,
    register,
    handleSubmit,
    reset,
    setValue,
    setError,
    clearErrors,
    trigger,
    watch,
    formState: { errors, isDirty }
  } = useForm<CompanyOnboardingFormValues>({
    resolver: zodResolver(companyOnboardingFormSchema),
    defaultValues: baseDefaults,
    mode: 'onBlur',
    reValidateMode: 'onBlur'
  })

  const companyName = watch('companyName')
  const companySlug = watch('companySlug')
  const companyTimezone = watch('companyTimezone')
  const fiscalYearCode = watch('fiscalYearCode')
  const fiscalYearName = watch('fiscalYearName')
  const fiscalYearStartDate = watch('fiscalYearStartDate')
  const fiscalYearEndDate = watch('fiscalYearEndDate')
  const accountsSetupMode = watch('accountsSetupMode')

  const timezoneOptions = useMemo(() => buildTimezoneOptions(detectedTimezone), [detectedTimezone])
  const slugPreview = useMemo(() => buildSlugPreview(companyName, companySlug), [companyName, companySlug])
  const ownerEmail = meQuery.data?.user?.email ?? 'Current signed-in account'
  const ownerName = meQuery.data?.user?.name ?? 'Owner account'
  const currentStep = createCompanyWizardSteps[activeStep]
  const isReviewStep = activeStep === createCompanyWizardSteps.length - 1
  const hasCompletedSetup = createdSetup !== null
  const progressValue = ((activeStep + 1) / createCompanyWizardSteps.length) * 100
  const nextStep = activeStep < createCompanyWizardSteps.length - 1 ? createCompanyWizardSteps[activeStep + 1] : null
  const canStartOver = !hasCompletedSetup && (isDirty || activeStep > 0)
  const isBusy = companySetupMutation.isPending
  const isFormLocked = isBusy || hasCompletedSetup

  const accountsSetupSummary = createdSetup?.accounts_setup ?? {
    mode: accountsSetupMode,
    created_count: accountsSetupMode === 'generated' ? STARTER_ACCOUNTS_PREVIEW_COUNT : 0
  }

  useEffect(() => {
    const timezone = resolveLocalTimezone()

    setDetectedTimezone(timezone)

    if (!companyTimezone) {
      setValue('companyTimezone', timezone, { shouldDirty: false, shouldValidate: true })
    }
  }, [companyTimezone, setValue])

  useEffect(() => {
    if (!fiscalYearStartDate) return

    const start = startOfMonth(new Date(`${fiscalYearStartDate}T00:00:00`))
    const normalizedStart = format(start, 'yyyy-MM-dd')
    const normalizedEnd = buildFiscalYearEndDate(normalizedStart)

    setValue('fiscalYearStartDate', normalizedStart, {
      shouldValidate: true,
      shouldDirty: normalizedStart !== baseDefaults.fiscalYearStartDate
    })
    setValue('fiscalYearEndDate', normalizedEnd, {
      shouldValidate: true,
      shouldDirty: normalizedEnd !== baseDefaults.fiscalYearEndDate
    })
  }, [fiscalYearStartDate, setValue])

  const resetDialog = () => {
    clearErrors()
    setFormStatus(null)
    setActiveStep(0)
    setCreatedSetup(null)
    reset({
      ...baseDefaults,
      companyTimezone: detectedTimezone
    })
  }

  const handleDialogClose = () => {
    if (isBusy) return

    const shouldRefresh = createdSetup !== null
    const closeReason = shouldRefresh ? 'completed' : 'cancel'

    resetDialog()
    onClose(closeReason)

    if (shouldRefresh) {
      router.refresh()
    }
  }

  const applyServerFieldErrors = (fieldErrors: CompanyOnboardingApiFieldErrors | null) => {
    if (!fieldErrors) return

    let nextActiveStep = activeStep

    for (const fieldName of Object.keys(FIELD_ERROR_MAP) as Array<keyof CompanyOnboardingFormValues>) {
      const nextMessage = FIELD_ERROR_MAP[fieldName].map(key => fieldErrors[key]?.[0]).find(Boolean)

      if (nextMessage) {
        setError(fieldName, { type: 'server', message: nextMessage })

        const stepIndex = findCreateCompanyStepIndexByField(fieldName)

        if (stepIndex >= 0) {
          nextActiveStep = Math.min(nextActiveStep, stepIndex)
        }
      }
    }

    if (nextActiveStep !== activeStep) {
      setActiveStep(nextActiveStep)
    }
  }

  const handleNextStep = async () => {
    setFormStatus(null)

    if (hasCompletedSetup) {
      setActiveStep(prev => Math.min(prev + 1, createCompanyWizardSteps.length - 1))

      return
    }

    if (!currentStep?.fields?.length) {
      setActiveStep(prev => Math.min(prev + 1, createCompanyWizardSteps.length - 1))

      return
    }

    const isValid = await trigger(currentStep.fields)

    if (!isValid) return

    setActiveStep(prev => Math.min(prev + 1, createCompanyWizardSteps.length - 1))
  }

  const handlePreviousStep = () => {
    setFormStatus(null)
    setActiveStep(prev => Math.max(prev - 1, 0))
  }

  const handleReset = () => {
    if (hasCompletedSetup || isBusy) return

    resetDialog()
  }

  const handleOpenWorkspace = () => {
    if (!createdSetup) return

    const nextTenant = createdSetup.tenant

    resetDialog()
    onClose('completed')
    onCreated?.(nextTenant)
    router.refresh()
  }

  const onSubmit = handleSubmit(async values => {
    clearErrors()
    setFormStatus(null)

    try {
      const response = await companySetupMutation.mutateAsync(values)

      setCreatedSetup(response.data)
      setFormStatus({
        type: 'success',
        message: `${response.data.tenant.name} is ready. Review the setup below, then open the workspace when you are ready.`
      })
    } catch (error) {
      if (error instanceof CompanySetupApiError) {
        applyServerFieldErrors(error.fieldErrors)
        setFormStatus({
          type: 'error',
          message: getCompanySetupErrorSummary(error)
        })

        return
      }

      const message = error instanceof Error ? error.message : 'Company creation failed.'

      setFormStatus({
        type: 'error',
        message
      })
    }
  })

  const handleFormSubmit = async (event: FormEvent<HTMLFormElement>) => {
    if (hasCompletedSetup) {
      event.preventDefault()

      return
    }

    if (!isReviewStep) {
      event.preventDefault()
      await handleNextStep()

      return
    }

    await onSubmit(event)
  }

  return (
    <Dialog open={open} onClose={handleDialogClose} fullWidth maxWidth='lg'>
      <DialogTitle sx={{ px: { xs: 3, md: 4 }, pt: 3, pb: 0 }}>Create company</DialogTitle>

      <DialogContent sx={{ p: { xs: 3, md: 4 } }}>
        <Stack component='form' spacing={4} onSubmit={handleFormSubmit}>
          <Stack spacing={2}>
            <Typography variant='overline' color='text.secondary' sx={{ letterSpacing: 0.8 }}>
              Step {activeStep + 1} of {createCompanyWizardSteps.length}
            </Typography>

            <LinearProgress
              variant='determinate'
              value={progressValue}
              sx={{
                height: 6,
                borderRadius: 999,
                bgcolor: 'action.hover',
                '& .MuiLinearProgress-bar': {
                  borderRadius: 999
                }
              }}
            />

            <OnboardingWizardStepper activeStep={activeStep} steps={createCompanyWizardSteps} />
          </Stack>

          <Stack spacing={0.75}>
            <Typography variant='h5'>
              {hasCompletedSetup ? 'Company ready to open' : (currentStep?.title ?? 'Create company')}
            </Typography>
            <Typography variant='body2' color='text.secondary'>
              {hasCompletedSetup
                ? isReviewStep
                  ? 'The company has been created successfully. Review the final summary, then open the workspace when you are ready.'
                  : 'The company has been created successfully. You can review the earlier setup details here before opening the workspace.'
                : (currentStep?.description ?? 'Enter the company details for the new workspace.')}
            </Typography>
          </Stack>

          {formStatus ? (
            <Paper
              variant='outlined'
              sx={{
                p: 2,
                borderColor: formStatus.type === 'error' ? 'error.main' : 'success.main',
                bgcolor: 'background.paper'
              }}
            >
              <Stack direction='row' spacing={1.5} alignItems='flex-start'>
                <Box
                  component='i'
                  className={formStatus.type === 'error' ? 'bx bx-error-circle' : 'bx bx-check-circle'}
                  sx={{
                    fontSize: 18,
                    mt: 0.2,
                    color: formStatus.type === 'error' ? 'error.main' : 'success.main'
                  }}
                />
                <Typography variant='body2'>{formStatus.message}</Typography>
              </Stack>
            </Paper>
          ) : null}

          {activeStep === 0 ? (
            <Stack spacing={3}>
              <CustomTextField
                label='Company name'
                placeholder='Acme Trading Inc.'
                fullWidth
                disabled={isFormLocked}
                {...register('companyName')}
                error={!!errors.companyName}
                helperText={errors.companyName?.message ?? 'Use the legal or operating name for the new company.'}
              />

              <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <CustomTextField
                    label='Workspace URL'
                    placeholder='acme-trading'
                    fullWidth
                    disabled={isFormLocked}
                    {...register('companySlug')}
                    error={!!errors.companySlug}
                    helperText={
                      errors.companySlug?.message ?? 'Optional. Leave blank to generate it from the company name.'
                    }
                  />
                </Grid>

                <Grid size={{ xs: 12, md: 6 }}>
                  <Controller
                    name='companyTimezone'
                    control={control}
                    render={({ field }) => (
                      <CustomAutocomplete
                        options={timezoneOptions}
                        value={field.value || null}
                        onChange={(_, value) => field.onChange(value ?? '')}
                        disabled={isFormLocked}
                        isOptionEqualToValue={(option, value) => option === value}
                        noOptionsText='No timezone match'
                        renderInput={params => (
                          <CustomTextField
                            {...params}
                            inputRef={field.ref}
                            label='Timezone'
                            placeholder='Select a timezone'
                            error={!!errors.companyTimezone}
                            helperText={
                              errors.companyTimezone?.message ??
                              'Used for company dates, audit timestamps, and period closing.'
                            }
                          />
                        )}
                      />
                    )}
                  />
                </Grid>
              </Grid>

              <Paper variant='outlined' sx={{ p: 2.5, bgcolor: 'action.hover' }}>
                <Stack spacing={1}>
                  <Typography variant='body2' fontWeight={600}>
                    Workspace preview
                  </Typography>
                  <Typography variant='body2' color='text.secondary'>
                    /{createdSetup?.tenant.slug ?? slugPreview}
                  </Typography>
                  <Typography variant='caption' color='text.secondary'>
                    This account will be assigned as the initial owner: {ownerEmail}
                  </Typography>
                </Stack>
              </Paper>
            </Stack>
          ) : null}

          {activeStep === 1 ? (
            <Stack spacing={3}>
              <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <CustomTextField
                    label='Fiscal year code'
                    placeholder={`FY${new Date().getFullYear()}`}
                    fullWidth
                    disabled={isFormLocked}
                    {...register('fiscalYearCode')}
                    error={!!errors.fiscalYearCode}
                    helperText={errors.fiscalYearCode?.message ?? `Example: FY${new Date().getFullYear()}`}
                  />
                </Grid>

                <Grid size={{ xs: 12, md: 6 }}>
                  <CustomTextField
                    label='Fiscal year name'
                    placeholder={`Fiscal Year ${new Date().getFullYear()}`}
                    fullWidth
                    disabled={isFormLocked}
                    {...register('fiscalYearName')}
                    error={!!errors.fiscalYearName}
                    helperText={errors.fiscalYearName?.message ?? `Example: Fiscal Year ${new Date().getFullYear()}`}
                  />
                </Grid>
              </Grid>

              <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <Controller
                    name='fiscalYearStartDate'
                    control={control}
                    render={({ field, fieldState }) => (
                      <AppReactDatepicker
                        showYearDropdown
                        disabled={isFormLocked}
                        id='create-company-fiscal-year-start-date'
                        onChange={(date: Date | null) => field.onChange(date ? format(date, 'yyyy-MM-dd') : '')}
                        selected={field.value ? new Date(`${field.value}T00:00:00`) : startOfMonth(new Date())}
                        filterDate={(date: Date) => date.getDate() === 1}
                        customInput={
                          <CustomTextField
                            label='Start date'
                            fullWidth
                            error={!!fieldState.error}
                            helperText={fieldState.error?.message ?? 'Choose the first day of your fiscal year.'}
                          />
                        }
                      />
                    )}
                  />
                </Grid>

                <Grid size={{ xs: 12, md: 6 }}>
                  <Controller
                    name='fiscalYearEndDate'
                    control={control}
                    render={({ field, fieldState }) => (
                      <AppReactDatepicker
                        showYearDropdown
                        id='create-company-fiscal-year-end-date'
                        disabled
                        onChange={() => {}}
                        selected={field.value ? new Date(`${field.value}T00:00:00`) : null}
                        customInput={
                          <CustomTextField
                            slotProps={{ htmlInput: { readOnly: true } }}
                            label='End date'
                            fullWidth
                            error={!!fieldState.error}
                            helperText={fieldState.error?.message ?? 'Calculated automatically from the start date.'}
                          />
                        }
                      />
                    )}
                  />
                </Grid>
              </Grid>

              <Paper variant='outlined' sx={{ p: 2.5, bgcolor: 'action.hover' }}>
                <Stack spacing={1}>
                  <Typography variant='body2' fontWeight={600}>
                    Ready-to-use fiscal year
                  </Typography>
                  <Typography variant='body2'>
                    {(createdSetup?.fiscal_year.name ?? fiscalYearName) || 'Fiscal year name'}{' '}
                    <Typography component='span' variant='body2' color='text.secondary'>
                      ({(createdSetup?.fiscal_year.code ?? fiscalYearCode) || `FY${new Date().getFullYear()}`})
                    </Typography>
                  </Typography>
                  <Typography variant='caption' color='text.secondary'>
                    Runs from {formatDisplayDate(createdSetup?.fiscal_year.start_date ?? fiscalYearStartDate)} to{' '}
                    {formatDisplayDate(createdSetup?.fiscal_year.end_date ?? fiscalYearEndDate)}.
                  </Typography>
                </Stack>
              </Paper>
            </Stack>
          ) : null}

          {activeStep === 2 ? (
            <Stack spacing={3}>
              <Paper variant='outlined' sx={{ p: { xs: 2.5, md: 3 }, bgcolor: 'action.hover' }}>
                <Stack spacing={2.5}>
                  <Stack spacing={0.75}>
                    <Typography variant='body2' fontWeight={700}>
                      Review starter chart of accounts
                    </Typography>
                    <Typography variant='body2' color='text.secondary'>
                      {getAccountsSetupModeDescription('generated')} You can rename, add, or archive accounts later
                      from Accounting Setup.
                    </Typography>
                  </Stack>

                  <Grid container spacing={2}>
                    {STARTER_ACCOUNTS_PREVIEW_GROUPS.map(group => (
                      <Grid key={group.id} size={{ xs: 12, md: 4 }}>
                        <Paper variant='outlined' sx={{ p: 2, height: '100%', bgcolor: 'background.paper' }}>
                          <Stack spacing={1.25}>
                            <Box>
                              <Typography variant='body2' fontWeight={700}>
                                {group.title}
                              </Typography>
                              <Typography variant='caption' color='text.secondary'>
                                {group.description}
                              </Typography>
                            </Box>

                            <Stack spacing={0.75}>
                              {group.accounts.map(accountName => (
                                <Stack key={accountName} direction='row' spacing={1} alignItems='center'>
                                  <Box
                                    component='i'
                                    className='bx bx-check'
                                    sx={{ fontSize: 16, color: 'success.main', flexShrink: 0 }}
                                  />
                                  <Typography variant='caption' color='text.secondary'>
                                    {accountName}
                                  </Typography>
                                </Stack>
                              ))}
                            </Stack>
                          </Stack>
                        </Paper>
                      </Grid>
                    ))}
                  </Grid>
                </Stack>
              </Paper>
            </Stack>
          ) : null}

          {isReviewStep ? (
            <Paper variant='outlined' sx={{ p: { xs: 3, md: 3.5 }, bgcolor: 'action.hover' }}>
              <Stack spacing={3}>
                <Stack spacing={1}>
                  <Typography variant='body1' fontWeight={600}>
                    {hasCompletedSetup ? 'Company ready to open' : 'Review the company setup'}
                  </Typography>

                  {hasCompletedSetup ? (
                    <Typography variant='body2' color='text.secondary'>
                      The company has been created successfully. Open the workspace when you are ready to switch into
                      it.
                    </Typography>
                  ) : null}
                </Stack>

                <Grid container spacing={3}>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Company name
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {createdSetup?.tenant.name ?? companyName ?? 'Not set'}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Workspace URL
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        /{createdSetup?.tenant.slug ?? slugPreview}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Timezone
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {createdSetup?.tenant.timezone ?? companyTimezone ?? detectedTimezone}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Owner access
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {ownerName}
                      </Typography>
                      <Typography variant='caption' color='text.secondary'>
                        {ownerEmail}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        First fiscal year
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {createdSetup?.fiscal_year.name ?? fiscalYearName ?? 'Fiscal year name'}
                      </Typography>
                      <Typography variant='caption' color='text.secondary'>
                        {createdSetup?.fiscal_year.code ?? fiscalYearCode ?? `FY${new Date().getFullYear()}`}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Date range
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {formatDisplayDate(createdSetup?.fiscal_year.start_date ?? fiscalYearStartDate)}
                      </Typography>
                      <Typography variant='caption' color='text.secondary'>
                        to {formatDisplayDate(createdSetup?.fiscal_year.end_date ?? fiscalYearEndDate)}
                      </Typography>
                    </Stack>
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <Stack spacing={0.75}>
                      <Typography variant='caption' color='text.secondary'>
                        Starting accounts
                      </Typography>
                      <Typography variant='body2' fontWeight={600}>
                        {getAccountsSetupModeLabel(accountsSetupSummary.mode)}
                      </Typography>
                      <Typography variant='caption' color='text.secondary'>
                        {accountsSetupSummary.mode === 'generated'
                          ? `${accountsSetupSummary.created_count} starter accounts will be ready when you open the workspace.`
                          : 'Starter accounts will be ready when you open the workspace.'}
                      </Typography>
                    </Stack>
                  </Grid>
                </Grid>

                <Divider />

                <Typography variant='body2' color='text.secondary'>
                  {hasCompletedSetup
                    ? `${createdSetup?.tenant.name ?? companyName ?? 'The company'} is ready. Open the workspace to continue with it as the active company.`
                    : `When you create the company, we'll set up your workspace, first fiscal year, and ${getAccountsSetupModeDescription(accountsSetupMode).toLowerCase()}`}
                </Typography>
              </Stack>
            </Paper>
          ) : null}

          <Stack spacing={1.5}>
            <Typography variant='caption' color='text.secondary'>
              {hasCompletedSetup
                ? 'You can keep refining company settings after you enter the workspace.'
                : 'You can adjust these settings later if needed.'}
            </Typography>

            <Stack
              direction={{ xs: 'column', sm: 'row' }}
              spacing={2}
              justifyContent='space-between'
              alignItems={{ xs: 'stretch', sm: 'center' }}
            >
              <Button
                type='button'
                variant='outlined'
                color='secondary'
                sx={{ visibility: activeStep === 0 || isReviewStep ? 'hidden' : 'visible' }}
                disabled={isBusy}
                onClick={handlePreviousStep}
              >
                Back
              </Button>

              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                {!hasCompletedSetup ? (
                  <Button
                    type='button'
                    variant='text'
                    color='secondary'
                    sx={{ visibility: !isReviewStep ? 'visible' : 'hidden' }}
                    disabled={!canStartOver || isBusy}
                    onClick={handleReset}
                  >
                    Start over
                  </Button>
                ) : null}

                <Button type='button' variant='text' color='secondary' disabled={isBusy} onClick={handleDialogClose}>
                  {hasCompletedSetup ? 'Done later' : 'Cancel'}
                </Button>

                {hasCompletedSetup ? (
                  <>
                    {!isReviewStep ? (
                      <Button type='button' variant='text' color='secondary' disabled={isBusy} onClick={handleNextStep}>
                        {nextStep ? `Next: ${nextStep.title}` : 'Next'}
                      </Button>
                    ) : null}

                    <Button type='button' variant='contained' onClick={handleOpenWorkspace}>
                      Open workspace
                    </Button>
                  </>
                ) : isReviewStep ? (
                  <Button type='submit' variant='contained' loading={companySetupMutation.isPending}>
                    Create company
                  </Button>
                ) : (
                  <Button type='button' variant='contained' disabled={isBusy} onClick={handleNextStep}>
                    {nextStep ? `Next: ${nextStep.title}` : 'Next'}
                  </Button>
                )}
              </Stack>
            </Stack>
          </Stack>
        </Stack>
      </DialogContent>
    </Dialog>
  )
}

export default CreateCompanyDialog
