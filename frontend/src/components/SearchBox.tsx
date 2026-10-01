import { useState, type FormEvent } from 'react'
import type { SearchScope } from '../api/client'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { useSuggestions } from '../hooks/useSuggestions'
import { ErrorBanner } from './ErrorBanner'

interface SearchBoxProps {
  onSearch: (name: string, scope: SearchScope) => void
  onSelectSuggestion: (folderId: string) => void
}

export function SearchBox({ onSearch, onSelectSuggestion }: SearchBoxProps) {
  const [name, setName] = useState('')
  const [searchAllFolders, setSearchAllFolders] = useState(false)
  const debouncedName = useDebouncedValue(name, 300)
  const hasTypedText = debouncedName.trim().length > 0
  const { suggestions, hasError } = useSuggestions(debouncedName, hasTypedText)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (name.trim().length === 0) return
    onSearch(name, searchAllFolders ? 'all' : 'folder')
  }

  function handleSelectSuggestion(folderId: string) {
    setName('')
    onSelectSuggestion(folderId)
  }

  return (
    <form className="search-box" onSubmit={handleSubmit}>
      <input
        type="search"
        name="name"
        value={name}
        placeholder="Search files by exact name…"
        aria-label="Search files by exact name"
        maxLength={255}
        onChange={(event) => setName(event.target.value)}
      />
      <label className="scope-toggle">
        <input
          type="checkbox"
          checked={searchAllFolders}
          onChange={(event) => setSearchAllFolders(event.target.checked)}
        />
        All folders
      </label>
      <button type="submit" disabled={name.trim().length === 0}>
        Search
      </button>
      {hasError && <ErrorBanner message="Suggestions are unavailable right now." />}
      {suggestions.length > 0 && (
        <ul className="suggestions">
          {suggestions.map((suggestion) => {
            const parent = suggestion.parentPath[suggestion.parentPath.length - 1]
            return (
              <li key={suggestion.id}>
                <button
                  type="button"
                  className="suggestion"
                  onClick={() => handleSelectSuggestion(parent.id)}
                >
                  <span className="suggestion-name">{suggestion.name}</span>
                  <span className="suggestion-path">in {parent.name}</span>
                </button>
              </li>
            )
          })}
        </ul>
      )}
    </form>
  )
}
