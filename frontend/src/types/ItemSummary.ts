export type ItemType = 'folder' | 'file'

export interface ItemSummary {
  id: string
  type: ItemType
  name: string
  parentId: string | null
}
