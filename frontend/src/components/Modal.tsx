import type { ReactNode } from 'react'

interface ModalProps {
  title: string
  children: ReactNode
}

export function Modal({ title, children }: ModalProps) {
  return (
    <div className="modal-overlay">
      <div className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <h2 id="modal-title">{title}</h2>
        {children}
      </div>
    </div>
  )
}
