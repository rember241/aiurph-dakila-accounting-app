'use client'

import { useMemo, useState } from 'react'

import { useRouter } from 'next/navigation'

import { toast, ToastContainer } from 'react-toastify'

import type { CreateEntrySubmitValues } from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import CustomPageHeader from '@components/app/CustomPageHeader'
import { useCreateJournalEntryMutation } from '@/features/(protected)/accounting/transactions/journal-entries/api/journal-entries.queries'
import CreateJournalEntryForm from '@views/(protected)/accounting/transactions/journal-entries/new/components/CreateJournalEntryForm'
import {
  useCreateJournalNumberMutation,
  useJournalNumbersQuery
} from '@/features/(protected)/accounting/setup/transaction-series/api/transaction-series.queries'
import type {
  JournalNumberColumn,
  JournalNumberSubmitValues
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'
import {
  useCreateFundSourceMutation,
  useFundSourcesQuery
} from '@/features/(protected)/accounting/setup/fund-sources/api/fund-sources.queries'
import type {
  FundSourceColumn,
  FundSourceSubmitValues
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import { useCreateTaxMutation, useTaxesQuery } from '@/features/(protected)/accounting/setup/taxes/api/taxes.queries'
import type { TaxColumn, TaxSubmitValues } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

import { groupAccounts } from '@/utils/journal-entries/groupAccounts'
import NewJournalNumberModal from '@views/(protected)/accounting/transactions/journal-entries/new/components/NewJournalNumberModal'
import NewFundSourceModal from '@views/(protected)/accounting/transactions/journal-entries/new/components/NewFundSourceModal'
import NewTaxModal from '@views/(protected)/accounting/transactions/journal-entries/new/components/NewTaxModal'

function NewJournalEntryPage() {
  // Router hook to redirect to other pages
  const router = useRouter()

  // New journal number, fund source, and tax modal states
  const [isJournalNumberModalOpen, setIsJournalNumberModalOpen] = useState<boolean>(false)
  const [isFundSourceModalOpen, setIsFundSourceModalOpen] = useState<boolean>(false)
  const [isTaxModalOpen, setIsTaxModalOpen] = useState<boolean>(false)

  // Custom TanStack Query hooks to fetch data needed to create entries
  const { data: journalNumbersData, isPending: isJournalNumbersFetching } = useJournalNumbersQuery({
    isPaginated: false
  })

  const { data: fundSourcesData, isPending: isFundSourcesFetching } = useFundSourcesQuery({
    isPaginated: false
  })

  const { data: taxesData, isPending: isTaxesFetching } = useTaxesQuery({
    isPaginated: false
  })

  const { data: accountsData, isPending: isAccountsFetching } = useAccountsTreeQuery()

  // Cast the fetched data into their proper types
  const journalNumbers = (journalNumbersData?.data as JournalNumberColumn[]) ?? []
  const fundSources = (fundSourcesData?.data as FundSourceColumn[]) ?? []
  const taxes = (taxesData?.data as TaxColumn[]) ?? []

  // Group posting accounts by their core types
  const accounts = useMemo(() => {
    if (!accountsData?.data.length) return []

    return groupAccounts(accountsData.data)
  }, [accountsData])

  // TanStack Query hook to submit a new entry
  const {
    mutateAsync: submitEntry,
    isPending: isEntrySubmitting,
    isSuccess: isEntrySubmissionSuccessful
  } = useCreateJournalEntryMutation()

  // TanStack Query hook to submit a new journal number
  const { mutateAsync: submitJournalNumber, isPending: isJournalNumberSubmitting } = useCreateJournalNumberMutation()

  // TanStack Query hook to submit a new fund source
  const { mutateAsync: submitFundSource, isPending: isFundSourceSubmitting } = useCreateFundSourceMutation()

  // TanStack Query hook to submit a new tax rate
  const { mutateAsync: submitTax, isPending: isTaxSubmitting } = useCreateTaxMutation()

  // Function to handle the submission of the new entry
  const handleEntrySubmission = async (newJournalEntry: CreateEntrySubmitValues) => {
    await toast.promise(submitEntry(newJournalEntry), {
      pending: `${newJournalEntry.status === 'posted' ? 'Posting' : 'Saving'} journal entry...`,
      success: `Journal ${newJournalEntry.status === 'posted' ? 'posted' : 'saved as draft'} successfully! Redirecting to journal entries page...`,
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error ${newJournalEntry.status === 'posted' ? 'posting' : 'saving'} entry`
        }
      }
    })

    setTimeout(() => router.push('/accounting/transactions/journal-entries'), 1500)
  }

  // Function to handle the submission of a new journal number
  const handleJournalNumberSubmission = async (newJournalNumber: JournalNumberSubmitValues) => {
    await toast.promise(submitJournalNumber(newJournalNumber), {
      pending: 'Adding journal number...',
      success: 'Journal number added!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error adding journal number`
        }
      }
    })

    setIsJournalNumberModalOpen(false)
  }

  // Function to handle the submission of a new fund source
  const handleFundSourceSubmission = async (newFundSource: FundSourceSubmitValues) => {
    await toast.promise(submitFundSource(newFundSource), {
      pending: 'Adding fund source...',
      success: 'Fund source added!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error adding fund source`
        }
      }
    })

    setIsFundSourceModalOpen(false)
  }

  // Function to handle the submission of a new tax
  const handleTaxSubmission = async (newTax: TaxSubmitValues) => {
    await toast.promise(submitTax(newTax), {
      pending: 'Adding tax rate...',
      success: 'Tax rate added!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error adding tax rate`
        }
      }
    })

    setIsTaxModalOpen(false)
  }

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={1500} position='top-center' />

      {/* New Journal Entry Header */}
      <CustomPageHeader title={'New Journal Entry'} description={'Record a new transaction'} />

      {/* Form */}
      <CreateJournalEntryForm
        journalNumbers={journalNumbers}
        fundSources={fundSources}
        taxes={taxes}
        accounts={accounts}
        onSubmit={handleEntrySubmission}
        isSubmitting={isEntrySubmitting}
        isSuccess={isEntrySubmissionSuccessful}
        onClose={() => router.push('/accounting/transactions/journal-entries')}
        isLoading={isJournalNumbersFetching || isFundSourcesFetching || isTaxesFetching || isAccountsFetching}
        openJournalNumberModal={() => setIsJournalNumberModalOpen(true)}
        openFundSourceModal={() => setIsFundSourceModalOpen(true)}
        openTaxModal={() => setIsTaxModalOpen(true)}
      />

      {/* New Journal Number Modal */}
      <NewJournalNumberModal
        open={isJournalNumberModalOpen}
        onClose={() => setIsJournalNumberModalOpen(false)}
        onSubmit={handleJournalNumberSubmission}
        isSubmitting={isJournalNumberSubmitting}
      />

      {/* New Fund Source Modal */}
      <NewFundSourceModal
        open={isFundSourceModalOpen}
        onClose={() => setIsFundSourceModalOpen(false)}
        onSubmit={handleFundSourceSubmission}
        isSubmitting={isFundSourceSubmitting}
      />

      {/* New Tax Modal */}
      <NewTaxModal
        open={isTaxModalOpen}
        onClose={() => setIsTaxModalOpen(false)}
        onSubmit={handleTaxSubmission}
        isSubmitting={isTaxSubmitting}
      />
    </>
  )
}

export default NewJournalEntryPage
