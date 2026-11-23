import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  FundSourceColumn,
  FundSourcesQuery,
  FundSourceSubmitValues,
  ModifiedFundSource,
  Paginated
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'

/**
 * Retrieves all fund sources data from the backend server
 *
 * @param {FundSourcesQuery} query
 */
export async function fetchFundSources(query: FundSourcesQuery): Promise<Paginated<FundSourceColumn>> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/fund-sources?${queryString}`)

  // Only return paginated data when isPaginated is set to true
  if (query.isPaginated) return response.data as Paginated<FundSourceColumn>

  return response.data
}

/**
 * Creates a new fund source
 *
 * @param {FundSourceSubmitValues} newFundSource - The validated data entered from a "New Fund Source" form
 */
export async function createFundSource(newFundSource: FundSourceSubmitValues) {
  const response = await laravelBrowserClient.post('/api/v1/fund-sources', newFundSource)

  return response.data
}

/**
 * Updates fund source data
 *
 * @param id The ID of the fund source to modify
 * @param data Contains the modified data for this fund source
 */
export async function editFundSource({ id, data }: ModifiedFundSource) {
  const response = await laravelBrowserClient.patch(`/api/v1/fund-sources/${id}`, data)

  return response.data
}

/**
 * Permanently deletes or soft deletes fund sources data
 * depending on if they've been used in journal entries or not
 *
 * @param fundSourceId The ID of the fund source to softly delete or permanently delete
 */
export async function deleteFundSource(fundSourceId: string) {
  const response = await laravelBrowserClient.delete(`/api/v1/fund-sources/${fundSourceId}`)

  return response.data
}

/**
 * Converts a fund source query object into a URL-encoded query string.
 * Empty or undefined properties in the query object are excluded
 * from the resulting string.
 *
 * @param {FundSourcesQuery} query
 * @returns {string}
 */
function toQueryString(query: FundSourcesQuery): string {
  const queryParameters = new URLSearchParams()

  if (query.sort) queryParameters.set('sort', query.sort)
  if (query.order) queryParameters.set('order', query.order)
  if (query.search) queryParameters.set('search', query.search)
  if (query.status) queryParameters.set('status', query.status)
  if (query.perPage) queryParameters.set('per_page', String(query.perPage))
  if (query.currentPage) queryParameters.set('page', String(query.currentPage))
  if (query.isPaginated !== undefined) queryParameters.set('is_paginated', String(query.isPaginated))

  return queryParameters.toString()
}
