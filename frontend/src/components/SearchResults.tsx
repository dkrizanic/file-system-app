import type { ItemDetail } from '../types/ItemDetail'
import type { Page } from '../types/Page'
import { useSuggestions } from '../hooks/useSuggestions'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { LoadingState } from '../components/LoadingState'
import { Pagination } from '../components/Pagination'

interface SearchResultsProps {
  query: string
  scopeLabel: string
  page: Page<ItemDetail> | null
  isLoading: boolean
  error: string | null
  onPageChange: (offset: number) => void
  onOpenFolder: (folderId: string) => void
  onClose: () => void
}

export function SearchResults({
  query,
  scopeLabel,
  page,
  isLoading,
  error,
  onPageChange,
  onOpenFolder,
  onClose,
}: SearchResultsProps) {
  const showNearMatches = page !== null && page.items.length === 0 && !isLoading && error === null
  const { suggestions } = useSuggestions(query, showNearMatches)

  return (
    <section className="search-results" aria-label="Search results">
      <header className="search-results-header">
        <h2>
          Results for "{query}" ({scopeLabel})
        </h2>
        <button type="button" onClick={onClose}>
          Back to folder
        </button>
      </header>
      {isLoading && page === null && <LoadingState />}
      {error !== null && <ErrorBanner message={error} />}
      {page !== null && page.items.length === 0 && (
        <EmptyState message={`No file is named exactly "${query}".`} />
      )}
      {showNearMatches && suggestions.length > 0 && (
        <div className="near-matches">
          <p className="near-matches-title">Files that start with "{query}":</p>
          <ul className="item-list">
            {suggestions.map((suggestion) => {
              const parent = suggestion.parentPath[suggestion.parentPath.length - 1]
              return (
                <li key={suggestion.id} className="item-row">
                  <span className="item-name">{suggestion.name}</span>
                  <button
                    type="button"
                    className="item-path"
                    onClick={() => onOpenFolder(parent.id)}
                  >
                    in {parent.name}
                  </button>
                </li>
              )
            })}
          </ul>
        </div>
      )}
      {page !== null && page.items.length > 0 && (
        <ul className="item-list">
          {page.items.map((item) => {
            const parent = item.parentPath[item.parentPath.length - 1]
            return (
              <li key={item.id} className="item-row">
                <span className="item-name">{item.name}</span>
                <button
                  type="button"
                  className="item-path"
                  onClick={() => onOpenFolder(parent.id)}
                >
                  in {parent.name}
                </button>
              </li>
            )
          })}
        </ul>
      )}
      {page !== null && (
        <Pagination
          total={page.total}
          limit={page.limit}
          offset={page.offset}
          onPageChange={onPageChange}
        />
      )}
    </section>
  )
}
