import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { ErrorBoundary } from './components/ErrorBoundary'
import { FolderBrowserPage } from './pages/FolderBrowserPage'
import './index.css'

const rootElement = document.getElementById('root')

if (rootElement !== null) {
  createRoot(rootElement).render(
    <StrictMode>
      <ErrorBoundary>
        <FolderBrowserPage />
      </ErrorBoundary>
    </StrictMode>,
  )
}
