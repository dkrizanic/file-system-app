import type { ItemMutationsResult } from '../hooks/useItemMutations'
import { DeleteConfirm } from './DeleteConfirm'
import { NameDialog } from './NameDialog'

interface ItemDialogsProps {
  mutations: ItemMutationsResult
}

export function ItemDialogs({ mutations }: ItemDialogsProps) {
  const { dialog, dialogError, pendingDelete } = mutations

  return (
    <>
      {dialog?.kind === 'create-folder' && (
        <NameDialog
          title="New folder"
          submitLabel="Create"
          error={dialogError}
          onSubmit={mutations.submitDialog}
          onCancel={mutations.closeDialog}
        />
      )}
      {dialog?.kind === 'create-file' && (
        <NameDialog
          title="New file"
          submitLabel="Create"
          error={dialogError}
          onSubmit={mutations.submitDialog}
          onCancel={mutations.closeDialog}
        />
      )}
      {dialog?.kind === 'rename' && (
        <NameDialog
          key={dialog.target.id}
          title={`Rename "${dialog.target.name}"`}
          submitLabel="Rename"
          initialName={dialog.target.name}
          error={dialogError}
          onSubmit={mutations.submitDialog}
          onCancel={mutations.closeDialog}
        />
      )}
      {pendingDelete !== null && (
        <DeleteConfirm
          itemName={pendingDelete.item.name}
          isFolder={pendingDelete.item.type === 'folder'}
          isDeleting={pendingDelete.isDeleting}
          error={pendingDelete.error}
          onConfirm={mutations.confirmDelete}
          onCancel={mutations.cancelDelete}
        />
      )}
    </>
  )
}
