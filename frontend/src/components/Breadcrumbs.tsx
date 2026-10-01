import type { PathEntry } from '../types/PathEntry'

interface BreadcrumbsProps {
  path: PathEntry[]
  currentName: string
  onNavigate: (folderId: string) => void
}

export function Breadcrumbs({ path, currentName, onNavigate }: BreadcrumbsProps) {
  return (
    <nav className="breadcrumbs" aria-label="Folder path">
      {path.map((entry) => (
        <span key={entry.id} className="breadcrumb-step">
          <button type="button" className="breadcrumb-link" onClick={() => onNavigate(entry.id)}>
            {entry.name}
          </button>
          <span className="breadcrumb-separator">/</span>
        </span>
      ))}
      <span className="breadcrumb-current">{currentName}</span>
    </nav>
  )
}
