'use client'

import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'

import { useRouter } from 'next/navigation'

import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import { format, startOfMonth } from 'date-fns'

import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import ButtonBase from '@mui/material/ButtonBase'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import LinearProgress from '@mui/material/LinearProgress'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import PageTitle from '@components/app/PageTitle'
import Logo from '@components/layout/shared/Logo'
import CustomAutocomplete from '@core/components/mui/Autocomplete'
import CustomTextField from '@core/components/mui/TextField'
import OnboardingWizardStepper from '@views/onboarding/company/components/OnboardingWizardStepper'

import AppReactDatepicker from '@/libs/styles/AppReactDatepicker'
import { useLogoutMutation, useMeQuery } from '@/features/auth/api/auth.queries'
import { DEFAULT_PROTECTED_REDIRECT, getPostOnboardingRedirect } from '@/features/auth/model/auth.schema'
import { CompanyOnboardingApiError } from '@/features/onboarding/api/company-onboarding.api'
import { useCompleteCompanyOnboardingMutation } from '@/features/onboarding/api/company-onboarding.queries'
import {
  getAccountsSetupModeDescription,
  getAccountsSetupModeLabel,
  STARTER_ACCOUNTS_PREVIEW_COUNT,
  STARTER_ACCOUNTS_PREVIEW_GROUPS
} from '@/features/onboarding/model/company-onboarding-accounts'
import {
  companyOnboardingSteps,
  findCompanyOnboardingStepIndexByField
} from '@/features/onboarding/model/company-onboarding.milestones'
import { companyOnboardingFormSchema } from '@/features/onboarding/model/company-onboarding.schema'
import {
  buildSlugPreview,
  buildTimezoneOptions,
  resolveLocalTimezone
} from '@/features/(protected)/users/tenants/utils/tenant-form'
import {
  CURRENT_TENANT_COOKIE,
  CURRENT_TENANT_COOKIE_MAX_AGE
} from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { getOrgUnitTypeLabel } from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type {
  CompanyOnboardingApiFieldErrors,
  CompanyOnboardingFormValues
} from '@/features/onboarding/model/company-onboarding.types'

type FormStatus = {
  type: 'success' | 'error' | 'info'
  message: string
} | null

const defaultFiscalYearStartDate = format(startOfMonth(new Date()), 'yyyy-MM-dd')
const defaultRootOrgUnitName = 'Head Office'

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

const KNOWN_ONBOARDING_ERROR_KEYS = new Set(Object.values(FIELD_ERROR_MAP).flat())

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

function getGlobalOnboardingErrorMessage(fieldErrors: CompanyOnboardingApiFieldErrors | null) {
  if (!fieldErrors) return null

  const preferredKeys = ['user']

  for (const key of preferredKeys) {
    const message = fieldErrors[key]?.[0]

    if (message) {
      return message
    }
  }

  for (const [key, messages] of Object.entries(fieldErrors)) {
    if (KNOWN_ONBOARDING_ERROR_KEYS.has(key)) {
      continue
    }

    const message = messages?.[0]

    if (message) {
      return message
    }
  }

  return null
}

function isOnboardingUnavailableError(error: CompanyOnboardingApiError) {
  const globalMessage = getGlobalOnboardingErrorMessage(error.fieldErrors)
  const sourceText = [error.message, globalMessage].filter(Boolean).join(' ')

  return (
    error.status === 409 || /already been completed|already has|no longer needed|no longer available/i.test(sourceText)
  )
}

function getOnboardingErrorSummary(error: CompanyOnboardingApiError) {
  const globalMessage = getGlobalOnboardingErrorMessage(error.fieldErrors)

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

  return error.message || "We couldn't finish setting up your company."
}

const baseDefaults: CompanyOnboardingFormValues = {
  companyName: '',
  companySlug: '',
  companyTimezone: '',
  createRootOrgUnit: true,
  rootOrgUnitName: defaultRootOrgUnitName,
  fiscalYearCode: `FY${new Date().getFullYear()}`,
  fiscalYearName: `Fiscal Year ${new Date().getFullYear()}`,
  fiscalYearStartDate: defaultFiscalYearStartDate,
  fiscalYearEndDate: buildFiscalYearEndDate(defaultFiscalYearStartDate),
  accountsSetupMode: 'generated'
}

function CompanyOnboardingPage() {
  const router = useRouter()
  const meQuery = useMeQuery()
  const logoutMutation = useLogoutMutation()
  const onboardingMutation = useCompleteCompanyOnboardingMutation()

  const [detectedTimezone, setDetectedTimezone] = useState('UTC')
  const [formStatus, setFormStatus] = useState<FormStatus>(null)
  const [activeStep, setActiveStep] = useState(0)

  const [createdSetup, setCreatedSetup] = useState<
    Awaited<ReturnType<typeof onboardingMutation.mutateAsync>>['data'] | null
  >(null)

  const [isLaunchingWorkspace, setIsLaunchingWorkspace] = useState(false)

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
  const createRootOrgUnit = watch('createRootOrgUnit')
  const rootOrgUnitName = watch('rootOrgUnitName')
  const fiscalYearCode = watch('fiscalYearCode')
  const fiscalYearName = watch('fiscalYearName')
  const fiscalYearStartDate = watch('fiscalYearStartDate')
  const fiscalYearEndDate = watch('fiscalYearEndDate')
  const accountsSetupMode = watch('accountsSetupMode')

  const ownerName = meQuery.data?.user?.name ?? 'Your account'
  const ownerEmail = meQuery.data?.user?.email ?? 'Current signed-in account'
  const timezoneOptions = useMemo(() => buildTimezoneOptions(detectedTimezone), [detectedTimezone])
  const slugPreview = useMemo(() => buildSlugPreview(companyName, companySlug), [companyName, companySlug])
  const currentStep = companyOnboardingSteps[activeStep]
  const hasCompletedSetup = createdSetup !== null
  const isReviewStep = activeStep === companyOnboardingSteps.length - 1
  const progressValue = ((activeStep + 1) / companyOnboardingSteps.length) * 100
  const nextStep = activeStep < companyOnboardingSteps.length - 1 ? companyOnboardingSteps[activeStep + 1] : null
  const canStartOver = !hasCompletedSetup && (isDirty || activeStep > 0)
  const isInteractionLocked = onboardingMutation.isPending || logoutMutation.isPending || isLaunchingWorkspace
  const isFormLocked = isInteractionLocked || hasCompletedSetup

  const accountsSetupSummary = createdSetup?.accounts_setup ?? {
    mode: accountsSetupMode,
    created_count: accountsSetupMode === 'generated' ? STARTER_ACCOUNTS_PREVIEW_COUNT : 0
  }

  const createdCompany = createdSetup?.tenant ?? null
  const createdRootOrgUnit = createdSetup?.organization.root_org_unit ?? null

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

  useEffect(() => {
    if (!createRootOrgUnit) {
      clearErrors('rootOrgUnitName')
    }
  }, [clearErrors, createRootOrgUnit])

  const applyServerFieldErrors = (fieldErrors: CompanyOnboardingApiFieldErrors | null) => {
    if (!fieldErrors) return

    let nextActiveStep = activeStep

    for (const fieldName of Object.keys(FIELD_ERROR_MAP) as Array<keyof CompanyOnboardingFormValues>) {
      const nextMessage = FIELD_ERROR_MAP[fieldName].map(key => fieldErrors[key]?.[0]).find(Boolean)

      if (nextMessage) {
        setError(fieldName, { type: 'server', message: nextMessage })

        const stepIndex = findCompanyOnboardingStepIndexByField(fieldName)

        if (stepIndex >= 0) {
          nextActiveStep = Math.min(nextActiveStep, stepIndex)
        }
      }
    }

    if (nextActiveStep !== activeStep) {
      setActiveStep(nextActiveStep)
    }
  }

  const launchIntoApp = async () => {
    setIsLaunchingWorkspace(true)

    try {
      const refreshedSession = await meQuery.refetch()

      const redirectTarget = refreshedSession.data?.user
        ? getPostOnboardingRedirect(refreshedSession.data.user)
        : DEFAULT_PROTECTED_REDIRECT

      router.replace(redirectTarget)
      router.refresh()
    } finally {
      setIsLaunchingWorkspace(false)
    }
  }

  const handleReset = () => {
    if (hasCompletedSetup || isLaunchingWorkspace) return

    clearErrors()
    setFormStatus(null)
    setActiveStep(0)
    setCreatedSetup(null)
    reset({
      ...baseDefaults,
      companyTimezone: detectedTimezone
    })
  }

  const handleLogout = async () => {
    try {
      await logoutMutation.mutateAsync()
    } finally {
      router.replace('/login')
      router.refresh()
    }
  }

  const handleNextStep = async () => {
    setFormStatus(null)

    if (hasCompletedSetup) {
      setActiveStep(prev => Math.min(prev + 1, companyOnboardingSteps.length - 1))

      return
    }

    if (!currentStep?.fields?.length) {
      setActiveStep(prev => Math.min(prev + 1, companyOnboardingSteps.length - 1))

      return
    }

    const isValid = await trigger(currentStep.fields)

    if (!isValid) return

    setActiveStep(prev => Math.min(prev + 1, companyOnboardingSteps.length - 1))
  }

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

  const onSubmit = handleSubmit(async values => {
    clearErrors()
    setFormStatus(null)

    try {
      const response = await onboardingMutation.mutateAsync(values)

      // Bootstrap the current_tenant_id cookie from the freshly-created tenant
      // so that server components (middleware / getServerCurrentTenantAccess)
      // can attach X-Company-ID to Laravel requests immediately after launch.
      // Without this cookie, /api/v1/me/access returns 404 and the entire
      // ability-gated menu renders empty.
      if (response.data?.tenant?.id) {
        document.cookie = [
          `${CURRENT_TENANT_COOKIE}=${response.data.tenant.id}`,
          'path=/',
          `max-age=${CURRENT_TENANT_COOKIE_MAX_AGE}`,
          'samesite=lax'
        ].join('; ')
      }

      setCreatedSetup(response.data)
      setFormStatus({
        type: 'success',
        message: `${response.data.tenant.name} is ready. Your company profile, structure, fiscal year, and accounting setup are prepared below.`
      })
    } catch (error) {
      if (error instanceof CompanyOnboardingApiError) {
        if (isOnboardingUnavailableError(error)) {
          const globalMessage = getGlobalOnboardingErrorMessage(error.fieldErrors)

          setFormStatus({
            type: 'info',
            message: globalMessage ?? 'Company setup is no longer needed for this account. Redirecting you to the app.'
          })
          await launchIntoApp()

          return
        }

        applyServerFieldErrors(error.fieldErrors)
        setFormStatus({
          type: 'error',
          message: getOnboardingErrorSummary(error)
        })

        return
      }

      const message = error instanceof Error ? error.message : "We couldn't finish setting up your company."

      setFormStatus({ type: 'error', message })
    }
  })

  return (
    <Box
      sx={{
        position: 'relative',
        minHeight: '100dvh',
        width: '100%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        bgcolor: 'background.default',
        p: { xs: 4, md: 8 }
      }}
    >
      <Button
        variant='text'
        color='secondary'
        loading={logoutMutation.isPending}
        disabled={onboardingMutation.isPending || isLaunchingWorkspace}
        onClick={handleLogout}
        sx={{
          position: 'absolute',
          top: { xs: 12, md: 20 },
          right: { xs: 12, md: 20 },
          minWidth: 'auto',
          px: 1,
          py: 0.5,
          zIndex: 1,
          color: 'text.secondary',
          '&:hover': {
            bgcolor: 'action.hover',
            color: 'text.primary'
          }
        }}
      >
        Sign out
      </Button>

      <Paper
        sx={{
          width: '100%',
          maxWidth: 1240,
          borderRadius: 5,
          overflow: 'hidden',
          boxShadow: 10,
          bgcolor: 'background.paper'
        }}
      >
        <Grid container>
          <Grid
            size={{ xs: 12, lg: 4 }}
            sx={{
              bgcolor: 'action.hover',
              borderRight: theme => ({ lg: `1px solid ${theme.palette.divider}` }),
              p: { xs: 4, md: 5 }
            }}
          >
            <Stack spacing={3} sx={{ height: '100%', justifyContent: 'space-between' }}>
              <Stack spacing={3}>
                <Box sx={{ display: 'flex', alignItems: 'center' }}>
                  <Logo />
                </Box>

                <Stack spacing={1.25}>
                  <Chip color='primary' variant='tonal' label='Company setup' sx={{ width: 'fit-content' }} />
                  <PageTitle title='Set up your company' icon='bx-buildings' variant='h4' />
                  <Typography variant='body2' color='text.secondary'>
                    We&apos;ll create your company profile, set up the first company structure unit, open your first
                    fiscal year, and prepare your accounting setup so you can start using the ERP right away.
                  </Typography>
                </Stack>

                <Paper variant='outlined' sx={{ p: 3, bgcolor: 'background.paper' }}>
                  <Stack spacing={2}>
                    <Typography variant='subtitle1' fontWeight={600}>
                      What you&apos;ll set up
                    </Typography>

                    <Stack spacing={1.5}>
                      <Box>
                        <Typography variant='body2' fontWeight={600}>
                          Company profile
                        </Typography>
                        <Typography variant='caption' color='text.secondary'>
                          Add your company name, URL, and timezone.
                        </Typography>
                      </Box>

                      <Box>
                        <Typography variant='body2' fontWeight={600}>
                          Company structure
                        </Typography>
                        <Typography variant='caption' color='text.secondary'>
                          Start with a head office unit you can expand into branches, departments, and teams later.
                        </Typography>
                      </Box>

                      <Box>
                        <Typography variant='body2' fontWeight={600}>
                          First fiscal year
                        </Typography>
                        <Typography variant='caption' color='text.secondary'>
                          Start with a ready-to-use accounting period.
                        </Typography>
                      </Box>

                      <Box>
                        <Typography variant='body2' fontWeight={600}>
                          Chart of accounts
                        </Typography>
                        <Typography variant='caption' color='text.secondary'>
                          Review the starter chart prepared for your first transactions.
                        </Typography>
                      </Box>

                      <Box>
                        <Typography variant='body2' fontWeight={600}>
                          Company admin
                        </Typography>
                        <Typography variant='caption' color='text.secondary'>
                          Your signed-in account will start as the primary admin.
                        </Typography>
                      </Box>
                    </Stack>
                  </Stack>
                </Paper>
              </Stack>

              <Paper variant='outlined' sx={{ p: 2.5, bgcolor: 'background.paper' }}>
                <Typography variant='body2' color='text.secondary'>
                  You can expand the structure with branches, departments, teams, and more users after the company is
                  created.
                </Typography>
              </Paper>
            </Stack>
          </Grid>

          <Grid size={{ xs: 12, lg: 8 }} sx={{ p: { xs: 4, md: 8 } }}>
            <Stack component='form' spacing={4} onSubmit={handleFormSubmit}>
              <Stack spacing={1.5}>
                <Typography variant='overline' color='text.secondary' sx={{ letterSpacing: 0.8 }}>
                  Step {activeStep + 1} of {companyOnboardingSteps.length}
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

                <OnboardingWizardStepper activeStep={activeStep} steps={companyOnboardingSteps} />
              </Stack>

              <Stack spacing={0.75}>
                <PageTitle
                  component='h2'
                  title={hasCompletedSetup ? 'Company ready to launch' : (currentStep?.title ?? 'Company profile')}
                  icon={hasCompletedSetup ? 'bx-check-shield' : (currentStep?.iconClassName ?? 'bx-buildings')}
                />
                <Typography variant='body2' color='text.secondary'>
                  {hasCompletedSetup
                    ? 'Your company has been created successfully. Review the final summary, then launch the ERP when you are ready.'
                    : (currentStep?.description ??
                      'Enter your company name, company URL, and timezone to create your company profile.')}
                </Typography>
              </Stack>

              {formStatus ? (
                <Paper
                  variant='outlined'
                  sx={{
                    p: 2,
                    borderColor:
                      formStatus.type === 'error'
                        ? 'error.main'
                        : formStatus.type === 'success'
                          ? 'success.main'
                          : 'info.main',
                    bgcolor: 'background.paper'
                  }}
                >
                  <Stack direction='row' spacing={1.5} alignItems='flex-start'>
                    <Box
                      component='i'
                      className={
                        formStatus.type === 'success'
                          ? 'bx bx-check-circle'
                          : formStatus.type === 'info'
                            ? 'bx bx-info-circle'
                            : 'bx bx-error-circle'
                      }
                      sx={{
                        fontSize: 18,
                        mt: 0.2,
                        color:
                          formStatus.type === 'error'
                            ? 'error.main'
                            : formStatus.type === 'success'
                              ? 'success.main'
                              : 'info.main'
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
                    helperText={errors.companyName?.message ?? 'Use your registered or trading business name.'}
                  />

                  <Grid container spacing={3}>
                    <Grid size={{ xs: 12, md: 6 }}>
                      <CustomTextField
                        label='Company URL'
                        placeholder='aiur-tech-corp'
                        fullWidth
                        disabled={isFormLocked}
                        {...register('companySlug')}
                        error={!!errors.companySlug}
                        helperText={
                          errors.companySlug?.message ??
                          'Optional. Leave this blank to generate it from your company name.'
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
                        Company URL preview
                      </Typography>
                      <Typography variant='body2' color='text.secondary'>
                        /{createdCompany?.slug ?? slugPreview}
                      </Typography>
                      <Typography variant='caption' color='text.secondary'>
                        This signed-in account will start as the primary company admin: {ownerEmail}
                      </Typography>
                    </Stack>
                  </Paper>
                </Stack>
              ) : null}

              {activeStep === 1 ? (
                <Stack spacing={3}>
                  <Controller
                    name='createRootOrgUnit'
                    control={control}
                    render={({ field, fieldState }) => (
                      <Stack spacing={2}>
                        <Stack spacing={1}>
                          <Typography variant='body2' fontWeight={600}>
                            Set up your initial company structure
                          </Typography>
                          <Typography variant='body2' color='text.secondary'>
                            Start your company structure with a root head office unit so roles, users, and reporting
                            lines have a home from day one.
                          </Typography>
                        </Stack>

                        <Grid container spacing={2}>
                          {[
                            {
                              value: true,
                              title: 'Create head office',
                              description: 'Recommended. Set up the first root org unit during onboarding.'
                            },
                            {
                              value: false,
                              title: 'Skip for now',
                              description: 'Create company structure later from the Company Structure workspace.'
                            }
                          ].map(option => {
                            const selected = field.value === option.value

                            return (
                              <Grid key={String(option.value)} size={{ xs: 12, md: 6 }}>
                                <ButtonBase
                                  onClick={() => field.onChange(option.value)}
                                  disabled={isFormLocked}
                                  sx={{
                                    width: '100%',
                                    borderRadius: 3,
                                    textAlign: 'left'
                                  }}
                                >
                                  <Paper
                                    variant='outlined'
                                    sx={{
                                      width: '100%',
                                      p: 2.5,
                                      borderColor: selected ? 'primary.main' : 'divider',
                                      bgcolor: selected ? 'action.hover' : 'background.paper'
                                    }}
                                  >
                                    <Stack spacing={1.5}>
                                      <Stack
                                        direction='row'
                                        spacing={1.5}
                                        alignItems='flex-start'
                                        justifyContent='space-between'
                                      >
                                        <Stack spacing={0.5}>
                                          <Typography variant='body2' fontWeight={700}>
                                            {option.title}
                                          </Typography>
                                          <Typography variant='body2' color='text.secondary'>
                                            {option.description}
                                          </Typography>
                                        </Stack>

                                        <Box
                                          component='i'
                                          className={selected ? 'bx bx-check-circle' : 'bx bx-circle'}
                                          sx={{
                                            fontSize: 20,
                                            color: selected ? 'primary.main' : 'text.disabled',
                                            flexShrink: 0
                                          }}
                                        />
                                      </Stack>

                                      <Chip
                                        color={option.value ? 'primary' : 'secondary'}
                                        variant='tonal'
                                        label={option.value ? 'Recommended' : 'Set up later'}
                                        sx={{ width: 'fit-content' }}
                                      />
                                    </Stack>
                                  </Paper>
                                </ButtonBase>
                              </Grid>
                            )
                          })}
                        </Grid>

                        <Typography variant='caption' color={fieldState.error ? 'error.main' : 'text.secondary'}>
                          {fieldState.error?.message ??
                            'The selected option controls whether onboarding creates the first root org unit.'}
                        </Typography>
                      </Stack>
                    )}
                  />

                  {createRootOrgUnit ? (
                    <>
                      <CustomTextField
                        label='Root org unit name'
                        placeholder={defaultRootOrgUnitName}
                        fullWidth
                        disabled={isFormLocked}
                        {...register('rootOrgUnitName')}
                        error={!!errors.rootOrgUnitName}
                        helperText={
                          errors.rootOrgUnitName?.message ??
                          'This will be created as your first Head Office unit and assigned to the company admin.'
                        }
                      />

                      <Paper variant='outlined' sx={{ p: 2.5, bgcolor: 'action.hover' }}>
                        <Stack spacing={1}>
                          <Typography variant='body2' fontWeight={600}>
                            Initial structure preview
                          </Typography>
                          <Typography variant='body2'>
                            {(createdRootOrgUnit?.name ?? rootOrgUnitName) || defaultRootOrgUnitName}{' '}
                            <Typography component='span' variant='body2' color='text.secondary'>
                              ({getOrgUnitTypeLabel(createdRootOrgUnit?.type ?? 'head_office')})
                            </Typography>
                          </Typography>
                          <Typography variant='caption' color='text.secondary'>
                            This creates the root unit for your structure. You can add branches, departments, and teams
                            after launch.
                          </Typography>
                        </Stack>
                      </Paper>
                    </>
                  ) : (
                    <Paper variant='outlined' sx={{ p: 2.5, bgcolor: 'action.hover' }}>
                      <Stack spacing={1}>
                        <Typography variant='body2' fontWeight={600}>
                          Structure setup skipped for now
                        </Typography>
                        <Typography variant='body2' color='text.secondary'>
                          Your company will be created without a root org unit. You can build the structure later from
                          the Company Structure workspace.
                        </Typography>
                      </Stack>
                    </Paper>
                  )}
                </Stack>
              ) : null}

              {activeStep === 2 ? (
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
                        helperText={errors.fiscalYearCode?.message ?? 'Example: FY2026'}
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
                        helperText={errors.fiscalYearName?.message ?? 'Example: Fiscal Year 2026'}
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
                            id='fiscal-year-start-date'
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
                            id='fiscal-year-end-date'
                            disabled={true}
                            onChange={() => {}}
                            selected={field.value ? new Date(`${field.value}T00:00:00`) : null}
                            customInput={
                              <CustomTextField
                                slotProps={{ htmlInput: { readOnly: true } }}
                                label='End date'
                                fullWidth
                                error={!!fieldState.error}
                                helperText={
                                  fieldState.error?.message ?? 'Calculated automatically from the start date.'
                                }
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

              {activeStep === 3 ? (
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
                        Review your setup
                      </Typography>
                    </Stack>

                    <Grid container spacing={3}>
                      <Grid size={{ xs: 12, md: 6 }}>
                        <Stack spacing={0.75}>
                          <Typography variant='caption' color='text.secondary'>
                            Company name
                          </Typography>
                          <Typography variant='body2' fontWeight={600}>
                            {createdCompany?.name ?? companyName ?? 'Not set'}
                          </Typography>
                        </Stack>
                      </Grid>

                      <Grid size={{ xs: 12, md: 6 }}>
                        <Stack spacing={0.75}>
                          <Typography variant='caption' color='text.secondary'>
                            Company URL
                          </Typography>
                          <Typography variant='body2' fontWeight={600}>
                            /{createdCompany?.slug ?? slugPreview}
                          </Typography>
                        </Stack>
                      </Grid>

                      <Grid size={{ xs: 12, md: 6 }}>
                        <Stack spacing={0.75}>
                          <Typography variant='caption' color='text.secondary'>
                            Timezone
                          </Typography>
                          <Typography variant='body2' fontWeight={600}>
                            {createdCompany?.timezone ?? companyTimezone ?? detectedTimezone}
                          </Typography>
                        </Stack>
                      </Grid>

                      <Grid size={{ xs: 12, md: 6 }}>
                        <Stack spacing={0.75}>
                          <Typography variant='caption' color='text.secondary'>
                            Primary admin
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
                            Initial company structure
                          </Typography>
                          <Typography variant='body2' fontWeight={600}>
                            {createdRootOrgUnit?.name ??
                              (createRootOrgUnit ? rootOrgUnitName || defaultRootOrgUnitName : 'Skipped for now')}
                          </Typography>
                          <Typography variant='caption' color='text.secondary'>
                            {createdRootOrgUnit
                              ? `${getOrgUnitTypeLabel(createdRootOrgUnit.type)} root unit created during onboarding.`
                              : createRootOrgUnit
                                ? `${getOrgUnitTypeLabel('head_office')} root unit will be created on launch.`
                                : 'No root org unit will be created during onboarding.'}
                          </Typography>
                        </Stack>
                      </Grid>

                      <Grid size={{ xs: 12, md: 6 }}>
                        <Stack spacing={0.75}>
                          <Typography variant='caption' color='text.secondary'>
                            Initial chart of accounts
                          </Typography>
                          <Typography variant='body2' fontWeight={600}>
                            {getAccountsSetupModeLabel(accountsSetupSummary.mode)}
                          </Typography>
                          <Typography variant='caption' color='text.secondary'>
                            {accountsSetupSummary.mode === 'generated'
                              ? `${accountsSetupSummary.created_count} starter accounts will be ready on launch.`
                              : 'Starter accounts will be ready on launch.'}
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
                    </Grid>

                    <Divider />

                    <Typography variant='body2' color='text.secondary'>
                      {hasCompletedSetup
                        ? `${createdCompany?.name ?? companyName ?? 'Your company'} is ready. Launch the ERP to continue into your company dashboard.`
                        : `When you create the company, we'll set up your profile, ${createRootOrgUnit ? 'your first company structure unit, ' : ''}first fiscal year, and ${getAccountsSetupModeDescription(accountsSetupMode).toLowerCase()}`}
                    </Typography>
                  </Stack>
                </Paper>
              ) : null}

              <Stack spacing={1.5}>
                <Typography variant='caption' color='text.secondary'>
                  You can review and update these settings later from Company Settings, Company Structure, and Setup.
                </Typography>

                <Stack
                  direction={{ xs: 'column', sm: 'row' }}
                  spacing={2}
                  justifyContent='flex-end'
                  alignItems={{ xs: 'stretch', sm: 'center' }}
                >
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                    <Button
                      type='button'
                      variant='text'
                      color='secondary'
                      sx={{ visibility: !hasCompletedSetup && !isReviewStep ? 'visible' : 'hidden' }}
                      disabled={!canStartOver || isInteractionLocked}
                      onClick={handleReset}
                    >
                      Start over
                    </Button>

                    {hasCompletedSetup ? (
                      <Button
                        type='button'
                        variant='contained'
                        loading={isLaunchingWorkspace}
                        onClick={() => void launchIntoApp()}
                      >
                        Launch ERP
                      </Button>
                    ) : isReviewStep ? (
                      <Button type='submit' variant='contained' loading={onboardingMutation.isPending}>
                        Create company
                      </Button>
                    ) : (
                      <Button type='button' variant='contained' disabled={isInteractionLocked} onClick={handleNextStep}>
                        {nextStep ? `Next: ${nextStep.title}` : 'Next'}
                      </Button>
                    )}
                  </Stack>
                </Stack>
              </Stack>
            </Stack>
          </Grid>
        </Grid>
      </Paper>
    </Box>
  )
}

export default CompanyOnboardingPage
