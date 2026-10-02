import { useCallback, useState } from 'react'
import { createFile, createFolder, deleteItem, renameItem } from '../api/client'
import { ApiError } from '../types/ApiError'
import type { ItemSummary } from '../types/ItemSummary'

export type DialogState =
  | { kind: 'create-folder' }
  | { kind: 'create-file' }
  | { kind: 'rename'; target: ItemSummary }
  | null

export interface PendingDelete {
  item: ItemSummary
  isDeleting: boolean
  error: string | null
}

function messageFor(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  const nameError = error.details.find((detail) => detail.field === 'name')
  return nameError !== undefined ? nameError.message : error.message
}

export interface ItemMutationsResult {
  dialog: DialogState
  dialogError: string | null
  openDialog: (next: NonNullable<DialogState>) => void
  closeDialog: () => void
  submitDialog: (name: string) => Promise<void>
  pendingDelete: PendingDelete | null
  requestDelete: (item: ItemSummary) => void
  cancelDelete: () => void
  confirmDelete: () => void
}

export function useItemMutations(folderId: string, refetch: () => void): ItemMutationsResult {
  const [dialog, setDialog] = useState<DialogState>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [pendingDelete, setPendingDelete] = useState<PendingDelete | null>(null)

  const openDialog = useCallback((next: NonNullable<DialogState>): void => {
    setDialog(next)
    setDialogError(null)
  }, [])

  const closeDialog = useCallback((): void => {
    setDialog(null)
  }, [])

  const cancelDelete = useCallback((): void => {
    setPendingDelete(null)
  }, [])

  const requestDelete = useCallback((item: ItemSummary): void => {
    setPendingDelete({ item, isDeleting: false, error: null })
  }, [])

  const submitDialog = useCallback(
    async (name: string): Promise<void> => {
      if (dialog === null) return
      try {
        if (dialog.kind === 'create-folder') {
          await createFolder({ parentId: folderId, name })
        } else if (dialog.kind === 'create-file') {
          await createFile({ parentId: folderId, name })
        } else {
          await renameItem(dialog.target.id, name)
        }
        setDialog(null)
        refetch()
      } catch (error) {
        if (dialog.kind === 'rename' && error instanceof ApiError && error.code === 'not_found') {
          setDialog(null)
          refetch()
          return
        }
        const fallback =
          dialog.kind === 'create-folder'
            ? 'The folder could not be created.'
            : dialog.kind === 'create-file'
              ? 'The file could not be created.'
              : 'The item could not be renamed.'
        setDialogError(messageFor(error, fallback))
        throw error
      }
    },
    [dialog, folderId, refetch],
  )

  const confirmDelete = useCallback((): void => {
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
  }, [pendingDelete, refetch])

  return {
    dialog,
    dialogError,
    openDialog,
    closeDialog,
    submitDialog,
    pendingDelete,
    requestDelete,
    cancelDelete,
    confirmDelete,
  }
}
