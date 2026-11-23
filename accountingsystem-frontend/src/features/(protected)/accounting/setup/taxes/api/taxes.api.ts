import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  TaxSubmitValues,
  Paginated,
  TaxColumn,
  TaxesQuery,
  ModifiedTax
} from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

type ResourceCollection<T> = {
  data: T[]
}

/**
 * Retrieves all taxes from the backend server
 *
 * @param {TaxesQuery} query
 * @return {Promise<Paginated<TaxColumn>>}
 */
export async function fetchTaxes(query: TaxesQuery): Promise<Paginated<TaxColumn>> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/taxes?${queryString}`)

  // Only return paginated data when isPaginated is set to true
  if (query.isPaginated) return response.data as Paginated<TaxColumn>

  return response.data
}

export async function fetchActiveTaxes(): Promise<TaxColumn[]> {
  const response = await laravelBrowserClient.get('/api/v1/taxes', { params: { is_paginated: false } })

  return readCollectionOrThrow<TaxColumn>(response.data)
}

/**
 * Creates a new tax
 *
 * @param {TaxSubmitValues} newTax - The validated data entered from a "New Tax" form
 */
export async function createTax(newTax: TaxSubmitValues) {
  const response = await laravelBrowserClient.post('/api/v1/taxes', newTax)

  return response.data
}

/**
 * Updates tax data
 *
 * @param id The ID of the tax to modify
 * @param data Contains the modified data for this tax
 */
export async function editTax({ id, data }: ModifiedTax) {
  const response = await laravelBrowserClient.patch(`/api/v1/taxes/${id}`, data)

  return response.data
}

/**
 * Permanently deletes or soft deletes tax data depending on if they've been used in journal entries or not
 *
 * @param taxId The ID of the tax to softly delete or permanently delete
 */
export async function deleteTax(taxId: string) {
  const response = await laravelBrowserClient.delete(`/api/v1/taxes/${taxId}`)

  return response.data
}

/**
 * Converts a tax query object into a URL-encoded query string.
 * Empty or undefined properties in the query object are excluded
 * from the resulting string.
 *
 * @param {TaxesQuery} query
 * @returns {string}
 */
function toQueryString(query: TaxesQuery): string {
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

function readCollectionOrThrow<T>(payload: ResourceCollection<T> | T[]): T[] {
  if (Array.isArray(payload)) {
    return payload
  }

  if (Array.isArray(payload?.data)) {
    return payload.data
  }

  throw new Error('Unexpected collection response.')
}
