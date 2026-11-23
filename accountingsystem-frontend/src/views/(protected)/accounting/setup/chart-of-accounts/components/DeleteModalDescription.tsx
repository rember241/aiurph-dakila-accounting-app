import type { AccountColumn } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

export const DeleteModalDescription = (removing?: AccountColumn | null) => {
  if (!removing) return null

  return (
    <>
      <p>Are you sure you want to permanently delete the following account?</p>

      <ul>
        <li>
          <strong>
            {removing.code} — {removing.name}
          </strong>
        </li>
      </ul>

      <p>
        <strong>Warning:</strong> This action is irreversible. Only unused custom accounts can be deleted. Accounts
        that are system-managed, linked to journal activity, or assigned in company defaults will be protected.
      </p>

      <p>Please confirm that this is the correct account before proceeding.</p>
    </>
  )
}
