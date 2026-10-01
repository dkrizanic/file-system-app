import type { ItemDetail } from '../types/ItemDetail'
import type { Page } from '../types/Page'
import { EmptyState } from './EmptyState'
import { ErrorBanner } from './ErrorBanner'
import { LoadingState } from './LoadingState'
import { Pagination } from './Pagination'

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
        <EmptyState message="No files match this search." />
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
