import { useMutation, useQuery } from '@tanstack/react-query'

import { toast } from 'react-toastify'

import type {
  DownloadIncomeStatementQuery,
  GenerateIncomeStatementQuery,
  GenerateIncomeStatementResponse
} from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'
import {
  downloadIncomeStatement,
  generateIncomeStatement
} from '@/features/(protected)/accounting/reports/income-statement/api/income-statement.api'
import { extractLaravelErrorMessage } from '@/lib/axios/auth/laravel-browser-client'
import downloadReport from '@/utils/downloadReport'

/**
 * Custom hook to fetch account data for the income statement
 * report based on the provided date range parameters as
 * well as the IDs of the accounts to be displayed in the table
 *
 * @param {GenerateIncomeStatementQuery} query The date range parameters as well as the account IDs
 */
export function useGenerateIncomeStatementQuery(query: GenerateIncomeStatementQuery) {
  return useQuery({
    queryKey: ['incomeStatement', query],
    queryFn: () => generateIncomeStatement(query),
    select: response => {
      // Transform the String representations of the decimal numbers from the backend in a Number data type
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

      const totalRevenue = response.totalRevenue.map((revenue: string) => Number(revenue))
      const totalExpense = response.totalExpense.map((expense: string) => Number(expense))
      const netTotal = response.netTotal.map((total: string) => Number(total))

      return {
        ...response,
        accounts,
        totalRevenue,
        totalExpense,
        netTotal
      } as GenerateIncomeStatementResponse
    },
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000,
    enabled: false // Disable automatic fetching of income statement data
  })
}

/**
 * Custom hook to download the income statement report based
 * on the provided date range, account IDs, and file type
 */
export function useDownloadIncomeStatement() {
  return useMutation({
    mutationFn: (query: DownloadIncomeStatementQuery) => downloadIncomeStatement(query),
    onSuccess: (blob, query) => {
      const filename =
        query.startDate === query.endDate
          ? `Income_Statement_${query.startDate}.${query.fileType}`
          : `Income_Statement_${query.startDate}-${query.endDate}.${query.fileType}`

      downloadReport(blob, filename)
    },
    onError: error => {
      const message = extractLaravelErrorMessage(error, 'Failed to download file')

      toast.error(message)
    }
  })
}
