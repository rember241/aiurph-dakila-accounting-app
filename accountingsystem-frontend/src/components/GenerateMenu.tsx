// React Imports
import type { ReactNode } from 'react'

// MUI Imports
import type { ChipProps } from '@mui/material/Chip'

// Type Imports
import type {
  VerticalMenuDataType,
  VerticalSectionDataType,
  VerticalSubMenuDataType,
  VerticalMenuItemDataType,
  HorizontalMenuDataType,
  HorizontalSubMenuDataType,
  HorizontalMenuItemDataType
} from '@/types/menuTypes'

// Component Imports
import { SubMenu as HorizontalSubMenu, MenuItem as HorizontalMenuItem } from '@menu/horizontal-menu'
import { SubMenu as VerticalSubMenu, MenuItem as VerticalMenuItem, MenuSection } from '@menu/vertical-menu'
import CustomChip from '@core/components/mui/Chip'

function stripAccessMeta<T extends { requiredAbilities?: string[]; requireAllAbilities?: boolean }>(item: T) {
  const rest = { ...item }

  delete rest.requiredAbilities
  delete rest.requireAllAbilities

  return rest as Omit<T, 'requiredAbilities' | 'requireAllAbilities'>
}

function stripSectionFlag<T extends { isSection: boolean }>(item: T) {
  const { isSection, ...rest } = item

  void isSection

  return rest as Omit<T, 'isSection'>
}

function resolveAdornment(value: ReactNode | ChipProps | undefined): ReactNode {
  if (value && typeof value === 'object' && 'label' in value) {
    return <CustomChip size='small' round='true' {...value} />
  }

  return value as ReactNode
}

function resolveRouteMatchProps(item: {
  exactMatch?: boolean
  activeUrl?: string
}): { exactMatch: true } | { exactMatch: false; activeUrl: string } | Record<string, never> {
  if (item.exactMatch === false && item.activeUrl) {
    return {
      exactMatch: false,
      activeUrl: item.activeUrl
    }
  }

  if (item.exactMatch === true) {
    return {
      exactMatch: true
    }
  }

  return {}
}

// Generate a menu from the menu data array
export const GenerateVerticalMenu = ({ menuData }: { menuData: VerticalMenuDataType[] }) => {
  // Hooks

  const renderMenuItems = (data: VerticalMenuDataType[]) => {
    // Use the map method to iterate through the array of menu data
    return data.map((item: VerticalMenuDataType, index) => {
      const menuSectionItem = item as VerticalSectionDataType
      const subMenuItem = item as VerticalSubMenuDataType
      const menuItem = item as VerticalMenuItemDataType

      // Check if the current item is a section
      if (menuSectionItem.isSection) {
        const { children, ...rest } = stripSectionFlag(stripAccessMeta(menuSectionItem))

        // If it is, return a MenuSection component and call generateMenu with the current menuSectionItem's children
        return (
          <MenuSection key={index} {...rest}>
            {children && renderMenuItems(children)}
          </MenuSection>
        )
      }

      // Check if the current item is a sub menu
      if (subMenuItem.children) {
        const { children, icon, prefix, suffix, ...rest } = stripAccessMeta(subMenuItem)

        const Icon = icon ? <i className={icon} /> : null

        const subMenuPrefix = resolveAdornment(prefix)
        const subMenuSuffix = resolveAdornment(suffix)

        // If it is, return a SubMenu component and call generateMenu with the current subMenuItem's children
        return (
          <VerticalSubMenu
            key={index}
            prefix={subMenuPrefix}
            suffix={subMenuSuffix}
            {...rest}
            {...(Icon && { icon: Icon })}
          >
            {children && renderMenuItems(children)}
          </VerticalSubMenu>
        )
      }

      // If the current item is neither a section nor a sub menu, return a MenuItem component
      const { label, icon, prefix, suffix, exactMatch, activeUrl, ...rest } = stripAccessMeta(menuItem)

      // Localize the href
      const href = rest.href

      const Icon = icon ? <i className={icon} /> : null

      const menuItemPrefix = resolveAdornment(prefix)
      const menuItemSuffix = resolveAdornment(suffix)
      const routeMatchProps = resolveRouteMatchProps({ exactMatch, activeUrl })

      return (
        <VerticalMenuItem
          key={index}
          prefix={menuItemPrefix}
          suffix={menuItemSuffix}
          {...rest}
          href={href}
          {...routeMatchProps}
          {...(Icon && { icon: Icon })}
        >
          {label}
        </VerticalMenuItem>
      )
    })
  }

  return <>{renderMenuItems(menuData)}</>
}

// Generate a menu from the menu data array
export const GenerateHorizontalMenu = ({ menuData }: { menuData: HorizontalMenuDataType[] }) => {
  // Hooks

  const renderMenuItems = (data: HorizontalMenuDataType[]) => {
    // Use the map method to iterate through the array of menu data
    return data.map((item: HorizontalMenuDataType, index) => {
      const subMenuItem = item as HorizontalSubMenuDataType
      const menuItem = item as HorizontalMenuItemDataType

      // Check if the current item is a sub menu
      if (subMenuItem.children) {
        const { children, icon, prefix, suffix, ...rest } = stripAccessMeta(subMenuItem)

        const Icon = icon ? <i className={icon} /> : null

        const subMenuPrefix = resolveAdornment(prefix)
        const subMenuSuffix = resolveAdornment(suffix)

        // If it is, return a SubMenu component and call generateMenu with the current subMenuItem's children
        return (
          <HorizontalSubMenu
            key={index}
            prefix={subMenuPrefix}
            suffix={subMenuSuffix}
            {...rest}
            {...(Icon && { icon: Icon })}
          >
            {children && renderMenuItems(children)}
          </HorizontalSubMenu>
        )
      }

      // If the current item is not a sub menu, return a MenuItem component
      const { label, icon, prefix, suffix, exactMatch, activeUrl, ...rest } = stripAccessMeta(menuItem)

      // Localize the href
      const href = rest.href

      const Icon = icon ? <i className={icon} /> : null

      const menuItemPrefix = resolveAdornment(prefix)
      const menuItemSuffix = resolveAdornment(suffix)
      const routeMatchProps = resolveRouteMatchProps({ exactMatch, activeUrl })

      return (
        <HorizontalMenuItem
          key={index}
          prefix={menuItemPrefix}
          suffix={menuItemSuffix}
          {...rest}
          href={href}
          {...routeMatchProps}
          {...(Icon && { icon: Icon })}
        >
          {label}
        </HorizontalMenuItem>
      )
    })
  }

  return <>{renderMenuItems(menuData)}</>
}
