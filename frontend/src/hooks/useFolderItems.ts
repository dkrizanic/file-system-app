import { useCallback, useEffect, useState } from 'react'
import { listFolderItems, toApiError } from '../api/client'
import type { ApiError } from '../types/ApiError'
import type { ItemSummary } from '../types/ItemSummary'
import type { Page } from '../types/Page'

interface FolderItemsState {
  page: Page<ItemSummary> | null
  error: ApiError | null
  isLoading: boolean
}

export interface FolderItemsResult extends FolderItemsState {
  refetch: () => void
}

export function useFolderItems(
  folderId: string,
  limit: number,
  offset: number,
  enabled: boolean,
): FolderItemsResult {
  const [state, setState] = useState<FolderItemsState>({
    page: null,
    error: null,
    isLoading: enabled,
  })
  const [refreshCount, setRefreshCount] = useState(0)

  const refetch = useCallback(() => setRefreshCount((count) => count + 1), [])

  useEffect(() => {
    if (!enabled) return
    const controller = new AbortController()
    setState((previous) => ({ ...previous, error: null, isLoading: true }))
    listFolderItems(folderId, { limit, offset }, { signal: controller.signal })
      .then((page) => setState({ page, error: null, isLoading: false }))
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        setState({ page: null, error: toApiError(error), isLoading: false })
      })
    return () => controller.abort()
  }, [folderId, limit, offset, enabled, refreshCount])

  return { ...state, refetch }
}
