/**
 * Shape Laravel always returns. Mirrors AbstractApiController + the
 * DomainException renderer in bootstrap/app.php.
 */

/** Successful envelope. */
export interface ApiResponse<T> {
  data: T;
  message?: string;
  meta?: Record<string, unknown>;
}

/** Failure envelope (every DomainException flows through this shape). */
export interface ApiErrorEnvelope {
  error: {
    code: string;
    message: string;
    context?: Record<string, unknown>;
  };
}

/** Paginated list payload (catalog list, orders list, etc.). */
export interface PageMeta {
  page: number;
  pageSize: number;
  total: number;
  totalPages: number;
}
