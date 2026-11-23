'use client'

import { useMemo } from 'react'

import { useRouter } from 'next/navigation'

import { toast, ToastContainer } from 'react-toastify'

import dayjs from 'dayjs'

import {
  useEditJournalEntryMutation,
  useEditJournalEntryQuery
} from '@/features/(protected)/accounting/transactions/journal-entries/api/journal-entries.queries'
import CustomPageHeader from '@components/app/CustomPageHeader'
import type {
  EditEntryFormValues,
  EditEntrySubmitValues,
  ModifiedJournalEntry
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import JournalEntryFormSkeleton from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntryFormSkeleton'
import EditJournalEntryForm from '@views/(protected)/accounting/transactions/journal-entries/edit/components/EditJournalEntryForm'
import { useFundSourcesQuery } from '@/features/(protected)/accounting/setup/fund-sources/api/fund-sources.queries'
import { useTaxesQuery } from '@/features/(protected)/accounting/setup/taxes/api/taxes.queries'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import type { FundSourceColumn } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'
import type { TaxColumn } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import { groupAccounts } from '@/utils/journal-entries/groupAccounts'

function EditJournalEntryPage({ journalEntryId }: { journalEntryId: string }) {
  // Router hook to redirect to other pages
  const router = useRouter()

  // Custom TanStack Query hooks to fetch data needed to edit this entry
  const { data: journalEntryData } = useEditJournalEntryQuery(journalEntryId)

  const { data: fundSourcesData, isPending: isFundSourcesFetching } = useFundSourcesQuery({
    isPaginated: false
  })

  const { data: taxesData, isPending: isTaxesFetching } = useTaxesQuery({
    isPaginated: false
  })

  const { data: accountsData, isPending: isAccountsFetching } = useAccountsTreeQuery()

  // Cast the fetched data into their proper types
  const fundSources = (fundSourcesData?.data as FundSourceColumn[]) ?? []
  const taxes = (taxesData?.data as TaxColumn[]) ?? []

  // Modify the response of useEditJournalEntryQuery to the shape the default values of EditJournalEntryForm expects
  const defaultValues: EditEntryFormValues | null = useMemo(() => {
    if (!journalEntryData) return null

    const { date, ...otherFields } = journalEntryData

    return {
      date: dayjs(date),
      ...otherFields
    }
  }, [journalEntryData])

  // Group posting accounts by their core types
  const accounts = useMemo(() => {
    if (!accountsData?.data.length) return []

    return groupAccounts(accountsData.data)
  }, [accountsData])

  // Custom TanStack Query hook to submit the modified journal entry data
  const { mutateAsync, isPending: isSubmitting, isSuccess } = useEditJournalEntryMutation()

  // Function to handle the submission of the modified journal entry
  const handleSubmit = async (modifiedData: EditEntrySubmitValues) => {
    const modifiedEntry: ModifiedJournalEntry = {
      id: journalEntryId,
      data: modifiedData
    }

    await toast.promise(mutateAsync(modifiedEntry), {
      pending: `${modifiedData.status === 'posted' ? 'Posting' : 'Saving'} journal entry...`,
      success: `Journal ${modifiedData.status === 'posted' ? 'posted' : 'saved as draft'} successfully! Redirecting to journal entries page...`,
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error ${modifiedData.status === 'posted' ? 'posting' : 'saving'} entry`
        }
      }
    })

    setTimeout(() => router.push('/accounting/transactions/journal-entries'), 1500)
  }

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* Edit Journal Page Header */}
      <CustomPageHeader title='Edit Journal Entry' description='Modify the details of this entry' />

      {/* Form */}
      {!defaultValues || isFundSourcesFetching || isTaxesFetching || isAccountsFetching ? (
        <JournalEntryFormSkeleton />
      ) : (
        <EditJournalEntryForm
          defaultValues={defaultValues}
          isSuccess={isSuccess}
          onSubmit={entryData => handleSubmit(entryData)}
          onClose={() => router.push('/accounting/transactions/journal-entries')}
          fundSources={fundSources}
          taxes={taxes}
          accounts={accounts}
          isSubmitting={isSubmitting}
        />
      )}
    </>
  )
}

export default EditJournalEntryPage
