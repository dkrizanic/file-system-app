interface ErrorBannerProps {
  message: string
  onDismiss?: () => void
}

export function ErrorBanner({ message, onDismiss }: ErrorBannerProps) {
  return (
    <div className="error-banner" role="alert">
      <span>{message}</span>
      {onDismiss !== undefined && (
        <button type="button" className="button-link" onClick={onDismiss}>
          Dismiss
        </button>
      )}
    </div>
  )
}
