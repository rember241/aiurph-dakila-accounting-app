# Roles UI Module

This module is the tenant-aware frontend role management interface under the Users domain.
It is connected to the Laravel backend through Next.js API proxy routes.

## Included

- Search and filters (status/access/department)
- Sortable role table
- Create/Edit role drawer
- Delete confirmation modal for custom roles
- Dynamic permission matrix by role key
- Permission scope controls (`All Pages` vs `Per Page`)
- System vs custom role indicators
- Single and bulk actions (enable/disable, read/write)
- Confirmation dialogs
- Loading, empty, and no-results states

## Future Improvements

1. Connect user-role assignment screens to the live roles API.
2. Add granular permission scopes beyond read/write.
3. Add role conflict checks beyond the current system-role delete guard.
4. Surface role assignment details instead of count-only summaries.
