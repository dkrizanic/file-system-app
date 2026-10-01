import { useCallback, useEffect, useState } from 'react'
import { ROOT_FOLDER_ID } from '../constants'

const FOLDER_HASH_PATTERN = /^#\/folders\/(.+)$/

export function folderHash(folderId: string): string {
  return folderId === ROOT_FOLDER_ID ? '#/' : `#/folders/${folderId}`
}

function folderIdFromHash(hash: string): string {
  const match = FOLDER_HASH_PATTERN.exec(hash)
  return match === null ? ROOT_FOLDER_ID : decodeURIComponent(match[1])
}

export function useHashFolderId(): {
  folderId: string
  navigateToFolder: (folderId: string) => void
} {
  const [folderId, setFolderId] = useState(() => folderIdFromHash(window.location.hash))

  useEffect(() => {
    const onHashChange = () => setFolderId(folderIdFromHash(window.location.hash))
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [])

  const navigateToFolder = useCallback((nextFolderId: string) => {
    window.location.hash = folderHash(nextFolderId)
  }, [])

  return { folderId, navigateToFolder }
}
