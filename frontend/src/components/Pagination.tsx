interface PaginationProps {
  total: number
  limit: number
  offset: number
  onPageChange: (offset: number) => void
}

export function Pagination({ total, limit, offset, onPageChange }: PaginationProps) {
  if (total <= 0) return null

  const first = offset + 1
  const last = Math.min(offset + limit, total)
  const hasPrevious = offset > 0
  const hasNext = offset + limit < total

  return (
    <div className="pagination">
      <button
        type="button"
        disabled={!hasPrevious}
        onClick={() => onPageChange(Math.max(0, offset - limit))}
      >
        Previous
      </button>
      <span className="pagination-info">
        Showing {first}–{last} of {total}
      </span>
      <button type="button" disabled={!hasNext} onClick={() => onPageChange(offset + limit)}>
        Next
      </button>
    </div>
  )
}
