export interface FieldError {
  field: string
  message: string
}

export class ApiError extends Error {
  readonly code: string
  readonly details: readonly FieldError[]

  constructor(code: string, message: string, details: readonly FieldError[] = []) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.details = details
  }
}
