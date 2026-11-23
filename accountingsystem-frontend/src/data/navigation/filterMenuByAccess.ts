import { hasAllCurrentTenantAbilities, hasAnyCurrentTenantAbility } from '@/features/auth/access/model/access.schema'

import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'
import type {
  HorizontalMenuDataType,
  VerticalMenuDataType,
  VerticalSectionDataType,
  VerticalSubMenuDataType
} from '@/types/menuTypes'

function matchesRequiredAbilities(
  item: { requiredAbilities?: string[]; requireAllAbilities?: boolean },
  access: CurrentTenantAccessState
) {
  const requiredAbilities = item.requiredAbilities ?? []

  if (requiredAbilities.length === 0) {
    return true
  }

  return item.requireAllAbilities
    ? hasAllCurrentTenantAbilities(access.data, requiredAbilities)
    : hasAnyCurrentTenantAbility(access.data, requiredAbilities)
}

export function filterVerticalMenuByAccess(menuData: VerticalMenuDataType[], access: CurrentTenantAccessState): VerticalMenuDataType[] {
  return menuData.flatMap(item => {
    if ('isSection' in item && item.isSection) {
      const sectionItem = item as VerticalSectionDataType

      if (!matchesRequiredAbilities(sectionItem, access)) {
        return []
      }

      const children = filterVerticalMenuByAccess(sectionItem.children, access)

      if (children.length === 0) {
        return []
      }

      return [{ ...sectionItem, children }]
    }

    if ('children' in item && Array.isArray(item.children)) {
      const subMenuItem = item as VerticalSubMenuDataType

      if (!matchesRequiredAbilities(subMenuItem, access)) {
        return []
      }

      const children = filterVerticalMenuByAccess(subMenuItem.children, access)

      if (children.length === 0) {
        return []
      }

      return [{ ...subMenuItem, children }]
    }

    return matchesRequiredAbilities(item, access) ? [item] : []
  })
}

export function filterHorizontalMenuByAccess(
  menuData: HorizontalMenuDataType[],
  access: CurrentTenantAccessState
): HorizontalMenuDataType[] {
  return menuData.flatMap(item => {
    if ('children' in item && Array.isArray(item.children)) {
      if (!matchesRequiredAbilities(item, access)) {
        return []
      }

      const children: HorizontalMenuDataType[] = filterHorizontalMenuByAccess(item.children, access)

      if (children.length === 0) {
        return []
      }

      return [{ ...item, children }]
    }

    return matchesRequiredAbilities(item, access) ? [item] : []
  })
}
