import { useState } from 'react'
import { createFile, createFolder, deleteItem, renameItem } from '../api/client'
import { ErrorBoundary } from '../components/ErrorBoundary'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { DeleteConfirm } from '../components/DeleteConfirm'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { ItemList } from '../components/ItemList'
import { LoadingState } from '../components/LoadingState'
import { NameDialog } from '../components/NameDialog'
import { Pagination } from '../components/Pagination'
import { useFolderItems } from '../hooks/useFolderItems'
import { useItemDetail } from '../hooks/useItemDetail'
import { ApiError } from '../types/ApiError'
import type { ItemSummary } from '../types/ItemSummary'

const PAGE_LIMIT = 50

interface FolderViewProps {
  folderId: string
  rootId: string
  onNavigate: (folderId: string) => void
}

type DialogState =
  | { kind: 'create-folder' }
  | { kind: 'create-file' }
  | { kind: 'rename'; target: ItemSummary }
  | null

interface PendingDelete {
  item: ItemSummary
  isDeleting: boolean
  error: string | null
}

function messageFor(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  const nameError = error.details.find((detail) => detail.field === 'name')
  return nameError !== undefined ? nameError.message : error.message
}

export function FolderView({ folderId, rootId, onNavigate }: FolderViewProps) {
  const [offset, setOffset] = useState(0)
  const [dialog, setDialog] = useState<DialogState>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [pendingDelete, setPendingDelete] = useState<PendingDelete | null>(null)

  const { detail, error: detailError, isLoading: detailIsLoading } = useItemDetail(folderId)
  const { page, error: itemsError, isLoading: itemsIsLoading, refetch } = useFolderItems(
    folderId,
    PAGE_LIMIT,
    offset,
    detail !== null,
  )

  function openDialog(next: NonNullable<DialogState>): void {
    setDialog(next)
    setDialogError(null)
  }

  async function handleCreateFolder(name: string): Promise<void> {
    try {
      await createFolder({ parentId: folderId, name })
      setDialog(null)
      refetch()
    } catch (error) {
      setDialogError(messageFor(error, 'The folder could not be created.'))
      throw error
    }
  }

  async function handleCreateFile(name: string): Promise<void> {
    try {
      await createFile({ parentId: folderId, name })
      setDialog(null)
      refetch()
    } catch (error) {
      setDialogError(messageFor(error, 'The file could not be created.'))
      throw error
    }
  }

  async function handleRename(name: string): Promise<void> {
    if (dialog === null || dialog.kind !== 'rename') return
    try {
      await renameItem(dialog.target.id, name)
      setDialog(null)
      refetch()
    } catch (error) {
      if (error instanceof ApiError && error.code === 'not_found') {
        setDialog(null)
        refetch()
        return
      }
      setDialogError(messageFor(error, 'The item could not be renamed.'))
      throw error
    }
  }

  function confirmDelete(): void {
    if (pendingDelete === null || pendingDelete.isDeleting) return
    setPendingDelete({ ...pendingDelete, isDeleting: true, error: null })
    deleteItem(pendingDelete.item.id)
      .then(() => {
        setPendingDelete(null)
        refetch()
      })
      .catch((error: unknown) => {
        if (error instanceof ApiError && error.code === 'not_found') {
          setPendingDelete(null)
          refetch()
          return
        }
        setPendingDelete((current) =>
          current === null
            ? current
            : {
                ...current,
                isDeleting: false,
                error: messageFor(error, 'The item could not be deleted.'),
              },
        )
      })
  }

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
          <button type="button" onClick={() => openDialog({ kind: 'create-folder' })}>
            New folder
          </button>
          <button type="button" onClick={() => openDialog({ kind: 'create-file' })}>
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
            onRename={(item) => openDialog({ kind: 'rename', target: item })}
            onDelete={(item) => setPendingDelete({ item, isDeleting: false, error: null })}
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
      {dialog?.kind === 'create-folder' && (
        <NameDialog
          title="New folder"
          submitLabel="Create"
          error={dialogError}
          onSubmit={handleCreateFolder}
          onCancel={() => setDialog(null)}
        />
      )}
      {dialog?.kind === 'create-file' && (
        <NameDialog
          title="New file"
          submitLabel="Create"
          error={dialogError}
          onSubmit={handleCreateFile}
          onCancel={() => setDialog(null)}
        />
      )}
      {dialog?.kind === 'rename' && (
        <NameDialog
          key={dialog.target.id}
          title={`Rename "${dialog.target.name}"`}
          submitLabel="Rename"
          initialName={dialog.target.name}
          error={dialogError}
          onSubmit={handleRename}
          onCancel={() => setDialog(null)}
        />
      )}
      {pendingDelete !== null && (
        <DeleteConfirm
          itemName={pendingDelete.item.name}
          isFolder={pendingDelete.item.type === 'folder'}
          isDeleting={pendingDelete.isDeleting}
          error={pendingDelete.error}
          onConfirm={confirmDelete}
          onCancel={() => setPendingDelete(null)}
        />
      )}
    </section>
  )
}
