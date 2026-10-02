import type { ItemSummary } from '../types/ItemSummary'

interface ItemListProps {
  items: ItemSummary[]
  onOpenFolder: (folderId: string) => void
  onRename: (item: ItemSummary) => void
  onDelete: (item: ItemSummary) => void
}

export function ItemList({ items, onOpenFolder, onRename, onDelete }: ItemListProps) {
  return (
    <ul className="item-list">
      {items.map((item) => (
        <li key={item.id} className="item-row">
          {item.type === 'folder' ? (
            <button
              type="button"
              className="item-name item-name-folder"
              onClick={() => onOpenFolder(item.id)}
            >
              {item.name}
            </button>
          ) : (
            <span className="item-name">{item.name}</span>
          )}
          <span className="item-type">{item.type}</span>
          <button
            type="button"
            aria-label={`Rename "${item.name}"`}
            onClick={() => onRename(item)}
          >
            Rename
          </button>
          <button
            type="button"
            aria-label={`Delete "${item.name}"`}
            onClick={() => onDelete(item)}
          >
            Delete
          </button>
        </li>
      ))}
    </ul>
  )
}
