import { useEffect, useState } from 'react'
import { getRootFolder, toApiError } from '../api/client'
import type { ApiError } from '../types/ApiError'

interface RootFolderState {
  rootId: string | null
  error: ApiError | null
  isLoading: boolean
}

export function useRootFolderId(): RootFolderState {
  const [state, setState] = useState<RootFolderState>({
    rootId: null,
    error: null,
    isLoading: true,
  })

  useEffect(() => {
    const controller = new AbortController()
    getRootFolder({ signal: controller.signal })
      .then((root) => setState({ rootId: root.id, error: null, isLoading: false }))
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        setState({ rootId: null, error: toApiError(error), isLoading: false })
      })
    return () => controller.abort()
  }, [])

  return state
}
