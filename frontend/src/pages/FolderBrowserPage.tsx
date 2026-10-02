import { useCallback, useEffect, useState } from 'react'
import { searchItems, toApiError, type SearchScope } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { ErrorBoundary } from '../components/ErrorBoundary'
import { LoadingState } from '../components/LoadingState'
import { SearchBox } from '../components/SearchBox'
import { SearchResults } from '../components/SearchResults'
import { useHashFolderId } from '../hooks/useHashFolderId'
import { useRootFolderId } from '../hooks/useRootFolderId'
import type { ItemDetail } from '../types/ItemDetail'
import type { Page } from '../types/Page'
import { FolderView } from './FolderView'

const SEARCH_PAGE_LIMIT = 50

interface SearchViewState {
  query: string
  scope: SearchScope
  offset: number
  version: number
  page: Page<ItemDetail> | null
  isLoading: boolean
  error: string | null
}

export function FolderBrowserPage() {
  const { rootId, error: rootError, isLoading: rootIsLoading } = useRootFolderId()
  const { folderId, navigateToFolder } = useHashFolderId(rootId)
  const [searchView, setSearchView] = useState<SearchViewState | null>(null)

  const handleNavigate = useCallback(
    (targetFolderId: string) => {
      setSearchView(null)
      navigateToFolder(targetFolderId)
    },
    [navigateToFolder],
  )

  const handleSearch = useCallback((query: string, scope: SearchScope) => {
    setSearchView({
      query,
      scope,
      offset: 0,
      version: Date.now(),
      page: null,
      isLoading: true,
      error: null,
    })
  }, [])

  useEffect(() => {
    if (searchView === null || folderId === null) return
    const controller = new AbortController()
    searchItems(
      {
        name: searchView.query,
        scope: searchView.scope,
        folderId: searchView.scope === 'folder' ? folderId : undefined,
        limit: SEARCH_PAGE_LIMIT,
        offset: searchView.offset,
      },
      { signal: controller.signal },
    )
      .then((page) =>
        setSearchView((current) =>
          current === null ? current : { ...current, page, isLoading: false, error: null },
        ),
      )
      .catch((error: unknown) => {
        if (controller.signal.aborted) return
        setSearchView((current) =>
          current === null
            ? current
            : { ...current, isLoading: false, error: toApiError(error).message },
        )
      })
    return () => controller.abort()
    // The version field is the only fetch trigger; page updates keep it stable.
  }, [searchView === null ? undefined : searchView.version, folderId])

  const searchScopeLabel = searchView?.scope === 'all' ? 'all folders' : 'this folder'

  if (rootError !== null) {
    return (
      <main className="browser">
        <h1>File system</h1>
        <ErrorBanner message={rootError.message} />
      </main>
    )
  }

  if (rootIsLoading || rootId === null || folderId === null) {
    return (
      <main className="browser">
        <h1>File system</h1>
        <LoadingState label="Loading folder…" />
      </main>
    )
  }

  return (
    <main className="browser">
      <h1>File system</h1>
      <ErrorBoundary>
        <SearchBox onSearch={handleSearch} onSelectSuggestion={handleNavigate} />
      </ErrorBoundary>
      {searchView === null ? (
        <FolderView key={folderId} folderId={folderId} rootId={rootId} onNavigate={handleNavigate} />
      ) : (
        <SearchResults
          query={searchView.query}
          scopeLabel={searchScopeLabel}
          page={searchView.page}
          isLoading={searchView.isLoading}
          error={searchView.error}
          onPageChange={(offset) =>
            setSearchView((current) =>
              current === null
                ? current
                : { ...current, offset, version: Date.now(), isLoading: true, error: null },
            )
          }
          onOpenFolder={handleNavigate}
          onClose={() => setSearchView(null)}
        />
      )}
    </main>
  )
}
