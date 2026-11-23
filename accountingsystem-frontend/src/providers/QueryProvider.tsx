'use client'

import React from 'react'

import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

import SessionStatusNotice from '@/features/auth/components/SessionStatusNotice'


export default function QueryProvider({ children }: { children: React.ReactNode }) {
  const [client] = React.useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            // Disable window-focus refetching globally. Every query in the app
            // has its own staleTime (typically 10 s – 5 min), but with the
            // default refetchOnWindowFocus: true those 34+ queries all fire
            // simultaneously whenever the developer (or user) alt-tabs back
            // to the browser — spamming the backend even when nothing has
            // changed. Individual queries that genuinely need focus-triggered
            // freshness can opt back in by setting refetchOnWindowFocus: true.
            refetchOnWindowFocus: false
          }
        }
      })
  )

  return <QueryClientProvider client={client}><SessionStatusNotice />{children}</QueryClientProvider>
}
