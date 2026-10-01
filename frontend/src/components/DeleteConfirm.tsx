interface DeleteConfirmProps {
  itemName: string
  isFolder: boolean
  isDeleting: boolean
  error: string | null
  onConfirm: () => void
  onCancel: () => void
}

export function DeleteConfirm({
  itemName,
  isFolder,
  isDeleting,
  error,
  onConfirm,
  onCancel,
}: DeleteConfirmProps) {
  return (
    <div className="modal-overlay">
      <div className="modal" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-title">
        <h2 id="delete-confirm-title">Delete {itemName}?</h2>
        {isFolder && <p>Everything inside this folder will be deleted too.</p>}
        {error !== null && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        <div className="modal-actions">
          <button type="button" onClick={onCancel} disabled={isDeleting}>
            Cancel
          </button>
          <button type="button" className="danger" onClick={onConfirm} disabled={isDeleting}>
            {isDeleting ? 'Deleting…' : 'Delete'}
          </button>
        </div>
      </div>
    </div>
  )
}
