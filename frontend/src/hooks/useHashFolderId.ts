import { useCallback, useEffect, useState } from 'react'

const FOLDER_HASH_PATTERN = /^#\/folders\/(.+)$/

function folderHash(folderId: string, rootFolderId: string): string {
  return folderId === rootFolderId ? '#/' : `#/folders/${folderId}`
}

function folderIdFromHash(hash: string, rootFolderId: string): string {
  const match = FOLDER_HASH_PATTERN.exec(hash)
  if (match === null) return rootFolderId
  try {
    return decodeURIComponent(match[1])
  } catch {
    return rootFolderId
  }
}

export function useHashFolderId(rootFolderId: string | null): {
  folderId: string | null
  navigateToFolder: (folderId: string) => void
} {
  const [folderId, setFolderId] = useState<string | null>(() =>
    rootFolderId === null ? null : folderIdFromHash(window.location.hash, rootFolderId),
  )

  useEffect(() => {
    setFolderId(rootFolderId === null ? null : folderIdFromHash(window.location.hash, rootFolderId))
  }, [rootFolderId])

  useEffect(() => {
    if (rootFolderId === null) return
    const onHashChange = () => setFolderId(folderIdFromHash(window.location.hash, rootFolderId))
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [rootFolderId])

  const navigateToFolder = useCallback(
    (nextFolderId: string) => {
      if (rootFolderId === null) return
      window.location.hash = folderHash(nextFolderId, rootFolderId)
    },
    [rootFolderId],
  )

  return { folderId, navigateToFolder }
}
