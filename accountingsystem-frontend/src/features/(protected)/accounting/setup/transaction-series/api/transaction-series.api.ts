import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  JournalNumberColumn,
  JournalNumbersQuery,
  JournalNumberSubmitValues,
  ModifiedJournalNumber,
  Paginated
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

/**
 * Loads transaction-series records from the backend.
 *
 * The table uses paginated mode, but selectors can still ask for the raw list
 * shape when they need the whole series catalogue.
 */
export async function fetchJournalNumbers(query: JournalNumbersQuery): Promise<Paginated<JournalNumberColumn>> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/journal-numbers?${queryString}`)

  // Only return paginated data when isPaginated is set to true
  if (query.isPaginated) return response.data as Paginated<JournalNumberColumn>

  return response.data
}

/**
 * Creates a new transaction series entry.
 */
export async function createJournalNumber(newJournalNumber: JournalNumberSubmitValues) {
  const response = await laravelBrowserClient.post('/api/v1/journal-numbers', newJournalNumber)

  return response.data
}

/**
 * Updates an existing transaction series entry.
 */
export async function editJournalNumber({ id, data }: ModifiedJournalNumber) {
  const response = await laravelBrowserClient.patch(`/api/v1/journal-numbers/${id}`, data)

  return response.data
}

/**
 * Deletes a transaction series entry.
 *
 * The backend decides whether the record is safe to remove based on how it has
 * already been used.
 */
export async function deleteJournalNumber(journalNumberId: string) {
  const response = await laravelBrowserClient.delete(`/api/v1/journal-numbers/${journalNumberId}`)

  return response.data
}

/**
 * Builds the query string expected by the transaction-series endpoint.
 */
function toQueryString(query: JournalNumbersQuery): string {
  const queryParameters = new URLSearchParams()

  if (query.sort) queryParameters.set('sort', query.sort)
  if (query.order) queryParameters.set('order', query.order)
  if (query.search) queryParameters.set('search', query.search)
  if (query.status) queryParameters.set('status', query.status)
  if (query.perPage) queryParameters.set('per_page', String(query.perPage))
  if (query.currentPage) queryParameters.set('page', String(query.currentPage))
  if (query.resetInterval) queryParameters.set('reset_interval', String(query.resetInterval))
  if (query.isPaginated !== undefined) queryParameters.set('is_paginated', String(query.isPaginated))

  return queryParameters.toString()
}
