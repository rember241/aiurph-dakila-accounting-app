# User Management UI Module (UI-Only)

This module is an initial enterprise-ready UI implementation of ERP User Management.
It is currently mock-data driven and intentionally contains no backend/API integration.

## Structure

- `UserManagementPage.tsx`
- `useUserManagementController.ts`
- `components/UserManagementFilters.tsx`
- `components/UserManagementTable.tsx`
- `components/UserTableColumns.tsx`
- `components/UserFormDrawer.tsx`
- `components/UserProfilePanel.tsx`
- `components/UserManagementSkeleton.tsx`
- `components/UserManagementStateCard.tsx`

## Mock Data Source

- `src/features/users/user-management/mock/user-management.mock.ts`

## Domain Models

- `src/features/users/user-management/model/user-management.types.ts`
- `src/features/users/user-management/model/user-management.schema.ts`

## How to Replace Mock Data with API Later

1. Replace bootstrapping in `useUserManagementController.ts` with a query call.
2. Move `createUser`, `updateUser`, and `setUsersStatus` to mutation calls.
3. Keep UI props unchanged so page/components do not need rewrites.
4. Replace role/department option derivation with API lookups (if required by backend).

## Future Scalability Notes

1. Move filtering/sorting/pagination to server-side mode for large user volume.
2. Add permission granularity beyond `read/write` (scopes/role templates).
3. Add invitation flow, password reset flow, and account lockout states.
4. Add audit timeline per user (status changes, access changes, editor identity).
5. Add optimistic updates with rollback for mutation failures.
6. Add test coverage for filtering, bulk actions, and form validation.
