// Next Imports
import type { Metadata } from 'next'
import { redirect } from 'next/navigation'

// Component Imports
import Login from '@views/auth/Login'
import { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'
import { getServerAuthenticatedUser } from '@/lib/server-session'

export const metadata: Metadata = {
  title: 'Login',
  description: 'Login to your account'
}

const LoginPage = async () => {
  const user = await getServerAuthenticatedUser()

  if (user) {
    redirect(DEFAULT_PROTECTED_REDIRECT)
  }

  return <Login />
}

export default LoginPage
