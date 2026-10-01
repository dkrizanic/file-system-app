import type { ItemSummary } from './ItemSummary'
import type { PathEntry } from './PathEntry'

export interface ItemDetail extends ItemSummary {
  parentPath: PathEntry[]
}
