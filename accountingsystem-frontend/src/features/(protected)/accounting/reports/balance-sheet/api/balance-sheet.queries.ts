import { useMutation, useQuery } from '@tanstack/react-query'

import { toast } from 'react-toastify'

import type {
  DownloadBalanceSheetQuery,
  GenerateBalanceSheetQuery,
  GenerateBalanceSheetResponse
} from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'
import {
  downloadBalanceSheet,
  fetchBalanceSheet
} from '@/features/(protected)/accounting/reports/balance-sheet/api/balance-sheet.api'
import { extractLaravelErrorMessage } from '@/lib/axios/auth/laravel-browser-client'

import downloadReport from '@/utils/downloadReport'

/**
 * Custom hook to fetch account data for the Balance Sheet
 * report based on the provided date and the IDs of the
 * accounts to be displayed in the table
 *
 * @param {GenerateBalanceSheetQuery} query The date and account IDs
 */
export function useBalanceSheetQuery(query: GenerateBalanceSheetQuery) {
  return useQuery({
    queryKey: ['balanceSheet', query],
    queryFn: () => fetchBalanceSheet(query),
    select: (response: any) => {
      // Transforms the String representation of decimal numbers from the backend to the Number data type
      const accounts = response.accounts.map((account: any) => {
        const transactions = account.transactions.map((transaction: any) => {
          return {
            ...transaction,
            debit: Number(transaction.debit),
            credit: Number(transaction.credit),
            balance: Number(transaction.balance)
          }
        })

        let previousBalances = undefined

        if ('previousBalances' in account)
          previousBalances = account.previousBalances.map((balance: string) => Number(balance))

        return {
          ...account,
          balance: Number(account.balance),
          previousBalances: previousBalances,
          transactions: transactions
        }
      })

      const totalCurrentAssets = response.totalCurrentAssets.map((currentAsset: string) => Number(currentAsset))

      const totalNonCurrentAssets = response.totalNonCurrentAssets.map((nonCurrentAsset: string) =>
        Number(nonCurrentAsset)
      )

      const totalAssets = response.totalAssets.map((asset: string) => Number(asset))

      const totalCurrentLiabilities = response.totalCurrentLiabilities.map((currentLiability: string) =>
        Number(currentLiability)
      )

      const totalNonCurrentLiabilities = response.totalNonCurrentLiabilities.map((nonCurrentLiability: string) =>
        Number(nonCurrentLiability)
      )

      const totalLiabilities = response.totalLiabilities.map((liability: string) => Number(liability))
      const totalEquities = response.totalEquities.map((equity: string) => Number(equity))

      const totalLiabilitiesEquities = response.totalLiabilitiesEquities.map((liabilityEquity: string) =>
        Number(liabilityEquity)
      )

      return {
        ...response,
        accounts,
        totalCurrentAssets,
        totalNonCurrentAssets,
        totalAssets,
        totalCurrentLiabilities,
        totalNonCurrentLiabilities,
        totalLiabilities,
        totalEquities,
        totalLiabilitiesEquities
      } as GenerateBalanceSheetResponse
    },
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000,
    enabled: false // Disable automatic fetching of balance sheet data
  })
}

/**
 * Custom hook to download the balance sheet report based
 * on the provided date, account IDs, and file type
 */
export function useDownloadBalanceSheet() {
  return useMutation({
    mutationFn: (query: DownloadBalanceSheetQuery) => downloadBalanceSheet(query),
    onSuccess: (blob, query) => {
      const filename = `Balance_Sheet_${query.date}.${query.fileType}`

      downloadReport(blob, filename)
    },
    onError: error => {
      const message = extractLaravelErrorMessage(error, 'Failed to download file')

      toast.error(message)
    }
  })
}
