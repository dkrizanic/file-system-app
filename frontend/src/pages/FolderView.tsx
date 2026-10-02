import { useEffect, useState } from 'react'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { ErrorBoundary } from '../components/ErrorBoundary'
import { ItemDialogs } from '../components/ItemDialogs'
import { ItemList } from '../components/ItemList'
import { LoadingState } from '../components/LoadingState'
import { Pagination } from '../components/Pagination'
import { useFolderItems } from '../hooks/useFolderItems'
import { useItemDetail } from '../hooks/useItemDetail'
import { useItemMutations } from '../hooks/useItemMutations'

const PAGE_LIMIT = 50

interface FolderViewProps {
  folderId: string
  rootId: string
  onNavigate: (folderId: string) => void
}

export function FolderView({ folderId, rootId, onNavigate }: FolderViewProps) {
  const [offset, setOffset] = useState(0)

  const { detail, error: detailError, isLoading: detailIsLoading } = useItemDetail(folderId)
  const { page, error: itemsError, isLoading: itemsIsLoading, refetch } = useFolderItems(
    folderId,
    PAGE_LIMIT,
    offset,
    detail !== null,
  )
  const mutations = useItemMutations(folderId, refetch)

  // Deleting the last items of a page can leave the offset pointing past the
  // end; pull it back to the last page that still has items.
  useEffect(() => {
    if (page === null || page.items.length > 0 || page.total === 0 || offset === 0) return
    const lastPageOffset = (Math.ceil(page.total / page.limit) - 1) * page.limit
    if (offset > lastPageOffset) setOffset(lastPageOffset)
  }, [page, offset])

  if (detailIsLoading) return <LoadingState label="Loading folder…" />

  if (detailError !== null) {
    if (detailError.code === 'not_found') {
      return (
        <section className="not-found">
          <EmptyState message="This folder does not exist anymore." />
          <button type="button" onClick={() => onNavigate(rootId)}>
            Back to root
          </button>
        </section>
      )
    }
    return <ErrorBanner message={detailError.message} />
  }
  if (detail === null) return null

  return (
    <section className="folder-view">
      <Breadcrumbs path={detail.parentPath} currentName={detail.name} onNavigate={onNavigate} />
      <ErrorBoundary>
        <div className="toolbar">
          <button type="button" onClick={() => mutations.openDialog({ kind: 'create-folder' })}>
            New folder
          </button>
          <button type="button" onClick={() => mutations.openDialog({ kind: 'create-file' })}>
            New file
          </button>
        </div>
      </ErrorBoundary>
      <div className="listing">
        {itemsIsLoading && page === null && <LoadingState />}
        {itemsError !== null && <ErrorBanner message={itemsError.message} />}
        {page !== null && page.items.length === 0 && <EmptyState message="This folder is empty." />}
        {page !== null && page.items.length > 0 && (
          <ItemList
            items={page.items}
            onOpenFolder={onNavigate}
            onRename={(item) => mutations.openDialog({ kind: 'rename', target: item })}
            onDelete={mutations.requestDelete}
          />
        )}
        {page !== null && (
          <Pagination
            total={page.total}
            limit={page.limit}
            offset={offset}
            onPageChange={setOffset}
          />
        )}
      </div>
      <ItemDialogs mutations={mutations} />
    </section>
  )
}
