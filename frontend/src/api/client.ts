import { ApiError, type FieldError } from '../types/ApiError'
import type { ItemDetail } from '../types/ItemDetail'
import type { ItemSummary } from '../types/ItemSummary'
import type { Page } from '../types/Page'
import type { Suggestion } from '../types/Suggestion'

const BASE_URL = '/api'

export type SearchScope = 'folder' | 'all'

export interface CreateFolderInput {
  parentId: string | null
  name: string
}

export interface CreateFileInput {
  parentId: string
  name: string
}

export interface SearchInput {
  name: string
  scope: SearchScope
  folderId?: string
  limit: number
  offset: number
}

export interface SuggestionsPage {
  items: Suggestion[]
}

interface RequestOptions {
  method?: string
  body?: unknown
  signal?: AbortSignal
}

interface PageParams {
  limit: number
  offset: number
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function parseFieldErrors(value: unknown): FieldError[] {
  if (!Array.isArray(value)) return []
  const fieldErrors: FieldError[] = []
  for (const entry of value) {
    if (isRecord(entry) && typeof entry.field === 'string' && typeof entry.message === 'string') {
      fieldErrors.push({ field: entry.field, message: entry.message })
    }
  }
  return fieldErrors
}

async function parseErrorEnvelope(response: Response): Promise<ApiError> {
  let code = 'internal_error'
  let message = `The request failed with status ${response.status}.`
  let details: FieldError[] = []
  try {
    const body: unknown = await response.json()
    if (isRecord(body) && isRecord(body.error)) {
      if (typeof body.error.code === 'string') code = body.error.code
      if (typeof body.error.message === 'string') message = body.error.message
      if (response.status === 400 || response.status === 409) {
        details = parseFieldErrors(body.error.details)
      }
    }
  } catch {
    // A non-JSON error body keeps the generic status message.
  }
  return new ApiError(code, message, details)
}

export function toApiError(error: unknown): ApiError {
  return error instanceof ApiError
    ? error
    : new ApiError('internal_error', 'Something went wrong while talking to the server.')
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  try {
    const response = await fetch(`${BASE_URL}${path}`, {
      method: options.method,
      headers: options.body === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: options.signal,
    })
    if (!response.ok) throw await parseErrorEnvelope(response)
    if (response.status === 204) return undefined as T
    return (await response.json()) as T
  } catch (cause) {
    if (cause instanceof ApiError) throw cause
    if (cause instanceof DOMException && cause.name === 'AbortError') throw cause
    throw new ApiError('internal_error', 'The server could not be reached.')
  }
}

export function listFolderItems(
  folderId: string,
  params: PageParams,
  options: RequestOptions = {},
): Promise<Page<ItemSummary>> {
  const query = new URLSearchParams({
    limit: String(params.limit),
    offset: String(params.offset),
  })
  return request(`/folders/${encodeURIComponent(folderId)}/items?${query.toString()}`, options)
}

export function createFolder(input: CreateFolderInput, options: RequestOptions = {}): Promise<ItemSummary> {
  return request('/folders', { ...options, method: 'POST', body: input })
}

export function createFile(input: CreateFileInput, options: RequestOptions = {}): Promise<ItemSummary> {
  return request('/files', { ...options, method: 'POST', body: input })
}

export function getItem(itemId: string, options: RequestOptions = {}): Promise<ItemDetail> {
  return request(`/items/${encodeURIComponent(itemId)}`, options)
}

export function renameItem(itemId: string, name: string, options: RequestOptions = {}): Promise<ItemSummary> {
  return request(`/items/${encodeURIComponent(itemId)}`, { ...options, method: 'PATCH', body: { name } })
}

export function deleteItem(itemId: string, options: RequestOptions = {}): Promise<void> {
  return request(`/items/${encodeURIComponent(itemId)}`, { ...options, method: 'DELETE' })
}

export function searchItems(input: SearchInput, options: RequestOptions = {}): Promise<Page<ItemDetail>> {
  const query = new URLSearchParams({
    name: input.name,
    scope: input.scope,
    limit: String(input.limit),
    offset: String(input.offset),
  })
  if (input.folderId !== undefined) query.set('folderId', input.folderId)
  return request(`/search?${query.toString()}`, options)
}

export function getSuggestions(prefix: string, options: RequestOptions = {}): Promise<SuggestionsPage> {
  const query = new URLSearchParams({ prefix })
  return request(`/suggestions?${query.toString()}`, options)
}
