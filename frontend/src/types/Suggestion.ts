import type { PathEntry } from './PathEntry'

export interface Suggestion {
  id: string
  name: string
  parentPath: PathEntry[]
}
