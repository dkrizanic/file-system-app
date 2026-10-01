import { useEffect, useState } from 'react'
import { getItem, toApiError } from '../api/client'
import type { ApiError } from '../types/ApiError'
import type { ItemDetail } from '../types/ItemDetail'

interface ItemDetailState {
  detail: ItemDetail | null
  error: ApiError | null
  isLoading: boolean
}

export function useItemDetail(itemId: string): ItemDetailState {
  const [state, setState] = useState<ItemDetailState>({
    detail: null,
    error: null,
    isLoading: true,
  })

  useEffect(() => {
    const controller = new AbortController()
    setState({ detail: null, error: null, isLoading: true })
    getItem(itemId, { signal: controller.signal })
      .then((detail) => setState({ detail, error: null, isLoading: false }))
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        setState({ detail: null, error: toApiError(error), isLoading: false })
      })
    return () => controller.abort()
  }, [itemId])

  return state
}
