import { useState } from 'react'
import { createFile, createFolder, deleteItem, renameItem } from '../api/client'
import { ErrorBoundary } from '../components/ErrorBoundary'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { DeleteConfirm } from '../components/DeleteConfirm'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { ItemForm } from '../components/ItemForm'
import { ItemList } from '../components/ItemList'
import { LoadingState } from '../components/LoadingState'
import { Pagination } from '../components/Pagination'
import { ROOT_FOLDER_ID } from '../constants'
import { useFolderItems } from '../hooks/useFolderItems'
import { useItemDetail } from '../hooks/useItemDetail'
import { ApiError } from '../types/ApiError'
import type { ItemSummary } from '../types/ItemSummary'

const PAGE_LIMIT = 50

interface FolderViewProps {
  folderId: string
  onNavigate: (folderId: string) => void
}

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

export function FolderView({ folderId, onNavigate }: FolderViewProps) {
  const [offset, setOffset] = useState(0)
  const [createFolderError, setCreateFolderError] = useState<string | null>(null)
  const [createFileError, setCreateFileError] = useState<string | null>(null)
  const [renameTarget, setRenameTarget] = useState<ItemSummary | null>(null)
  const [renameError, setRenameError] = useState<string | null>(null)
  const [pendingDelete, setPendingDelete] = useState<PendingDelete | null>(null)

  const { detail, error: detailError, isLoading: detailIsLoading } = useItemDetail(folderId)
  const { page, error: itemsError, isLoading: itemsIsLoading, refetch } = useFolderItems(
    folderId,
    PAGE_LIMIT,
    offset,
    detail !== null,
  )

  async function handleCreateFolder(name: string): Promise<void> {
    setCreateFolderError(null)
    try {
      await createFolder({ parentId: folderId, name })
      refetch()
    } catch (error) {
      setCreateFolderError(messageFor(error, 'The folder could not be created.'))
      throw error
    }
  }

  async function handleCreateFile(name: string): Promise<void> {
    setCreateFileError(null)
    try {
      await createFile({ parentId: folderId, name })
      refetch()
    } catch (error) {
      setCreateFileError(messageFor(error, 'The file could not be created.'))
      throw error
    }
  }

  async function handleRename(name: string): Promise<void> {
    if (renameTarget === null) return
    setRenameError(null)
    try {
      await renameItem(renameTarget.id, name)
      setRenameTarget(null)
      refetch()
    } catch (error) {
      if (error instanceof ApiError && error.code === 'not_found') {
        setRenameTarget(null)
        refetch()
        return
      }
      setRenameError(messageFor(error, 'The item could not be renamed.'))
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
          <button type="button" onClick={() => onNavigate(ROOT_FOLDER_ID)}>
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
        <div className="forms">
          <ItemForm
            label="New folder name"
            submitLabel="Create folder"
            error={createFolderError}
            onSubmit={handleCreateFolder}
          />
          <ItemForm
            label="New file name"
            submitLabel="Create file"
            error={createFileError}
            onSubmit={handleCreateFile}
          />
          {renameTarget !== null && (
            <ItemForm
              key={renameTarget.id}
              label={`Rename "${renameTarget.name}"`}
              submitLabel="Rename"
              initialName={renameTarget.name}
              error={renameError}
              onSubmit={handleRename}
            />
          )}
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
            onRename={setRenameTarget}
            onDelete={(item) => setPendingDelete({ item, isDeleting: false, error: null })}
          />
        )}
        {page !== null && (
          <Pagination
            total={page.total}
            limit={page.limit}
            offset={page.offset}
            onPageChange={setOffset}
          />
        )}
      </div>
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
