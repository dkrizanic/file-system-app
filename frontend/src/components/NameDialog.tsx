import { useState, type FormEvent } from 'react'
import { Modal } from './Modal'

const NAME_MAX_LENGTH = 255

interface NameDialogProps {
  title: string
  submitLabel: string
  initialName?: string
  error: string | null
  onSubmit: (name: string) => Promise<void>
  onCancel: () => void
}

export function NameDialog({
  title,
  submitLabel,
  initialName = '',
  error,
  onSubmit,
  onCancel,
}: NameDialogProps) {
  const [name, setName] = useState(initialName)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      await onSubmit(name.trim())
    } catch {
      // The dialog stays open with the error the caller rendered.
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Modal title={title}>
      <form className="dialog-form" onSubmit={handleSubmit}>
        <input
          type="text"
          name="name"
          value={name}
          maxLength={NAME_MAX_LENGTH}
          required
          autoFocus
          disabled={isSubmitting}
          onChange={(event) => setName(event.target.value)}
        />
        {error !== null && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        <div className="modal-actions">
          <button type="button" onClick={onCancel} disabled={isSubmitting}>
            Cancel
          </button>
          <button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Saving…' : submitLabel}
          </button>
        </div>
      </form>
    </Modal>
  )
}
