import { useState, type FormEvent } from 'react'

const NAME_MAX_LENGTH = 255

interface ItemFormProps {
  label: string
  submitLabel: string
  initialName?: string
  error: string | null
  onSubmit: (name: string) => Promise<void>
}

export function ItemForm({ label, submitLabel, initialName = '', error, onSubmit }: ItemFormProps) {
  const [name, setName] = useState(initialName)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      await onSubmit(name)
      setName('')
    } catch {
      // The caller rendered the failure; the form stays open with the input.
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <form className="item-form" onSubmit={handleSubmit}>
      <label>
        <span className="form-label">{label}</span>
        <input
          type="text"
          name="name"
          value={name}
          maxLength={NAME_MAX_LENGTH}
          required
          disabled={isSubmitting}
          onChange={(event) => setName(event.target.value)}
        />
      </label>
      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? 'Saving…' : submitLabel}
      </button>
      {error !== null && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}
    </form>
  )
}
