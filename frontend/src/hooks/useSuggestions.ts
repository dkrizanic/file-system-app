import { useEffect, useState } from 'react'
import { getSuggestions } from '../api/client'
import type { Suggestion } from '../types/Suggestion'

interface SuggestionsState {
  suggestions: Suggestion[]
  hasError: boolean
}

export function useSuggestions(prefix: string, enabled: boolean): SuggestionsState {
  const [state, setState] = useState<SuggestionsState>({ suggestions: [], hasError: false })

  useEffect(() => {
    if (!enabled) {
      setState({ suggestions: [], hasError: false })
      return
    }
    const controller = new AbortController()
    getSuggestions(prefix, { signal: controller.signal })
      .then((page) => setState({ suggestions: page.items, hasError: false }))
      .catch(() => {
        if (controller.signal.aborted) return
        setState({ suggestions: [], hasError: true })
      })
    return () => controller.abort()
  }, [prefix, enabled])

  return state
}
