import { useMutation, useQuery } from '@tanstack/react-query'

import { toast } from 'react-toastify'

import type {
  DownloadTrialBalanceQuery,
  GenerateTrialBalanceQuery
} from '@/features/(protected)/accounting/reports/trial-balance/model/trial-balance.types'
import {
  downloadTrialBalance,
  fetchTrialBalance
} from '@/features/(protected)/accounting/reports/trial-balance/api/trial-balance.api'
import downloadReport from '@/utils/downloadReport'
import { extractLaravelErrorMessage } from '@/lib/axios/auth/laravel-browser-client'

/**
 * Custom hook to fetch account data for the Trial
 * Balance report based on the provided date
 *
 * @param {GenerateTrialBalanceQuery} query The date
 */
export function useTrialBalanceQuery(query: GenerateTrialBalanceQuery) {
  return useQuery({
    queryKey: ['trialBalance', query],
    queryFn: () => fetchTrialBalance(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000,
    enabled: false // Disable automatic fetching of trial balance data
  })
}

/**
 * Custom hook to download the trial balance
 * report based on the provided date
 */
export function useDownloadTrialBalance() {
  return useMutation({
    mutationFn: (query: DownloadTrialBalanceQuery) => downloadTrialBalance(query),
    onSuccess: (blob, query) => {
      const filename = `Trial_Balance_${query.date}.${query.fileType}`

      downloadReport(blob, filename)
    },
    onError: error => {
      const message = extractLaravelErrorMessage(error, 'Failed to download file')

      toast.error(message)
    }
  })
}
