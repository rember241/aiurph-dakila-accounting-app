'use client'

// React Imports
import { useEffect, useRef, useState } from 'react'

// MUI Imports
import Typography from '@mui/material/Typography'
import IconButton from '@mui/material/IconButton'
import InputAdornment from '@mui/material/InputAdornment'
import Checkbox from '@mui/material/Checkbox'
import Button from '@mui/material/Button'
import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import FormControlLabel from '@mui/material/FormControlLabel'
import Alert from '@mui/material/Alert'

// Hook Imports
import { Controller, useForm } from 'react-hook-form'

import { zodResolver } from '@hookform/resolvers/zod'
import type { z } from 'zod'

// Config Imports
import themeConfig from '@configs/themeConfig'

import { useLoginMutation } from '@/features/auth/api/auth.queries'

// Component Imports
import Link from '@components/Link'
import PageTitle from '@components/app/PageTitle'
import CustomTextField from '@core/components/mui/TextField'

import { CURRENT_TENANT_COOKIE } from '@/features/(protected)/users/tenants/model/current-tenant.schema'
import { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'
import { loginInputSchema } from '@/features/auth/model/auth.schema'
import { extractLaravelErrorMessage } from '@/lib/axios/auth/laravel-browser-client'

type LoginFormValues = z.infer<typeof loginInputSchema>
type FormStatus = { type: 'error' | 'success'; message: string } | null

const Login = () => {
  // States
  const [ready, setReady] = useState(false)
  const [isPasswordShown, setIsPasswordShown] = useState(false)
  const [formStatus, setFormStatus] = useState<FormStatus>(null)
  const loginMutation = useLoginMutation()
  const submitting = useRef(false)

  useEffect(() => {
    setReady(true)
  }, [])

  const handleClickShowPassword = () => setIsPasswordShown(show => !show)

  // Submit
  const {
    control,
    handleSubmit,
    setError,
    clearErrors,
    formState: { errors }
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginInputSchema),
    defaultValues: { email: '', password: '' }
  })

  const onSubmit = handleSubmit(async values => {
    if (submitting.current) return

    submitting.current = true
    clearErrors('root')
    setFormStatus(null)

    try {
      await loginMutation.mutateAsync(values)
      setFormStatus({ type: 'success', message: 'Login successful. Redirecting...' })

      // Laravel resolves the company from the new session.
      document.cookie = `${CURRENT_TENANT_COOKIE}=; path=/; max-age=0`

      // Keep the first page load on the origin that received the session cookie.
      window.location.replace(DEFAULT_PROTECTED_REDIRECT)
    } catch (err) {
      submitting.current = false

      const message = extractLaravelErrorMessage(err, 'Login failed')

      setError('root', { type: 'server', message })
      setFormStatus({ type: 'error', message })
    }
  })

  return (
    <Box
      sx={{
        minHeight: '100dvh',
        width: '100%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        bgcolor: 'background.default',
        p: 3
      }}
    >
      <Box
        sx={{
          position: 'relative',
          width: '100%',
          maxWidth: 480,
          bgcolor: 'background.paper',
          borderRadius: 3,
          border: '1px solid',
          borderColor: 'divider',
          boxShadow: theme => `0 28px 80px rgb(${theme.vars.palette.primary.mainChannel} / 0.14)`,
          p: { xs: 3, md: 8 }
        }}
      >
        <Stack spacing={5}>
          <Stack direction='row' spacing={2} alignItems='center'>
            <Box
              component='img'
              src='/images/logos/dakila-logo.png'
              alt='Dakila'
              sx={{ width: 44, height: 44, objectFit: 'contain' }}
            />
            <Typography
              component='span'
              sx={{ fontSize: '1.75rem', fontWeight: 700, letterSpacing: '0.01em', lineHeight: 1 }}
            >
              {themeConfig.templateName}
            </Typography>
          </Stack>

          <Stack spacing={1}>
            <PageTitle title='Welcome back' icon='bx-log-in-circle' />

            <Typography color='text.secondary'>Enter your details to continue to your accounting workspace.</Typography>
          </Stack>

          <Stack component='form' method='post' spacing={4} noValidate autoComplete='off' onSubmit={onSubmit}>
            {formStatus && (
              <Alert
                severity={formStatus.type}
                icon={false}
                sx={{ width: '100%', '& .MuiAlert-message': { width: '100%' } }}
              >
                <Stack direction='row' spacing={1} alignItems='flex-start'>
                  <Box
                    component='i'
                    className={formStatus.type === 'error' ? 'bx bx-error-circle' : 'bx bx-check-circle'}
                    sx={{ fontSize: 20, lineHeight: 1.2, mt: 0.2 }}
                  />
                  <Typography variant='body2'>{formStatus.message}</Typography>
                </Stack>
              </Alert>
            )}
            <Controller
              name='email'
              control={control}
              render={({ field }) => (
                <CustomTextField
                  {...field}
                  id='login-email'
                  fullWidth
                  label='Email'
                  placeholder='Enter your email'
                  error={!!errors.email}
                  helperText={errors.email?.message}
                />
              )}
            />
            <Controller
              name='password'
              control={control}
              render={({ field }) => (
                <CustomTextField
                  {...field}
                  id='login-password'
                  fullWidth
                  placeholder='············'
                  label='Password'
                  type={isPasswordShown ? 'text' : 'password'}
                  error={!!errors.password}
                  helperText={errors.password?.message}
                  slotProps={{
                    input: {
                      endAdornment: (
                        <InputAdornment position='end'>
                          <IconButton
                            edge='end'
                            onClick={handleClickShowPassword}
                            onMouseDown={e => e.preventDefault()}
                          >
                            <i className={isPasswordShown ? 'bx-hide' : 'bx-show'} />
                          </IconButton>
                        </InputAdornment>
                      )
                    }
                  }}
                />
              )}
            />

            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
              <FormControlLabel control={<Checkbox />} label='Remember me' />
              <Typography color='primary.main' component={Link} sx={{ textAlign: 'right' }}>
                Forgot password?
              </Typography>
            </Box>
            <Button disabled={!ready || formStatus?.type === 'success'} loading={loginMutation.isPending} fullWidth variant='contained' type='submit'>
              {loginMutation.isPending ? 'Logging in...' : 'Login'}
            </Button>
          </Stack>
        </Stack>
      </Box>
    </Box>
  )
}

export default Login
