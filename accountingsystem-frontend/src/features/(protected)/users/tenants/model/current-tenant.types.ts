export type CurrentTenantState = {
  data: {
    tenantId: string | null
  }
}

export type UpdateCurrentTenantInput = {
  tenantId: string
}
