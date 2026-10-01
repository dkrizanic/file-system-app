import { Component, type ReactNode } from 'react'
import { ErrorBanner } from './ErrorBanner'

interface ErrorBoundaryProps {
  children: ReactNode
}

interface ErrorBoundaryState {
  error: Error | null
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { error: null }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { error }
  }

  render(): ReactNode {
    if (this.state.error !== null) {
      return (
        <ErrorBanner
          message="Something went wrong while rendering this part of the page."
          onDismiss={() => this.setState({ error: null })}
        />
      )
    }
    return this.props.children
  }
}
