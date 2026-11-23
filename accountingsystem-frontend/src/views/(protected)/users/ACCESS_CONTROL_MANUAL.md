# ERP Users & Roles Manual (UI Version)

This module follows role-based access control (RBAC):

- Roles page defines access levels and permission checkboxes.
- User Management page assigns roles to users and controls user account status.

## Standard Workflow

1. Open `/users/roles`.
2. Create or edit a role.
3. Set role status (`Enabled` or `Disabled`).
4. Set role access level (`Read` or `Write`).
5. Choose permission scope:
   - `All Pages` for global permission control
   - `Per Page` for module-level permission control
6. Select role permission checkboxes.
7. Save the role.
8. Open `/users/user-management`.
9. Create or edit a user and assign the correct role.
10. Set user account status (`Enabled` or `Disabled`).
11. Save and verify in the user profile drawer.

## What to Configure in Each Page

- Configure in Roles:
  - Access level
  - Permission checkboxes
  - Role status
- Configure in User Management:
  - User profile data
  - Role assignment
  - User account status

## Do / Don’t

- Do:
  - Keep permissions centralized in Roles.
  - Use User status for suspension/reactivation only.
  - Review role memberships regularly.
- Don’t:
  - Maintain duplicate permission logic in User Management.
  - Assign inactive roles to active users.

## Backend Integration Notes

When API integration is added:

1. Backend must enforce permissions from role assignments, not from front-end state.
2. User endpoints should return role and effective permissions for display.
3. Audit logs should track:
   - Role permission changes
   - User role assignment changes
   - User enable/disable actions
