import type { VerticalMenuDataType } from '@/types/menuTypes'

import verticalMenuData from './verticalMenuData'

export type BreadcrumbItem = {
  label: string
  icon: string
  href?: string
}

const menu = verticalMenuData()
const defaultIcon = 'bx-file'

function findMenuTrail(path: string, items: VerticalMenuDataType[]): BreadcrumbItem[] | undefined {
  for (const item of items) {
    if (typeof item.label !== 'string') continue

    const crumb = {
      label: item.label,
      icon: 'icon' in item && typeof item.icon === 'string' ? item.icon : defaultIcon
    }

    if ('children' in item && Array.isArray(item.children)) {
      const trail = findMenuTrail(path, item.children)

      if (trail) return [crumb, ...trail]
    } else if ('href' in item && typeof item.href === 'string') {
      if (path === item.href) return [crumb]

      if ('exactMatch' in item && item.exactMatch === false && path.startsWith(`${item.href}/`)) {
        return [{ ...crumb, href: item.href }]
      }
    }
  }
}

const homeCrumb = {
  label: 'Home',
  href: '/home',
  icon: findMenuTrail('/home', menu)?.[0]?.icon ?? 'bx-home-circle'
}

const accountingSetup = findMenuTrail('/accounting/setup/accounts', menu)?.[0]
const closing = { label: 'Closing', icon: 'bx-calendar' }

const journalPageIcons = new Map([
  ['new', 'bx-plus-circle'],
  ['edit', 'bx-pencil']
])

const pagesOutsideMenu: Record<string, { section?: BreadcrumbItem; icon: string }> = {
  '/accounting/setup/defaults': { section: accountingSetup, icon: 'bx-cog' },
  '/accounting/closing/period': { section: closing, icon: 'bx-calendar-check' },
  '/accounting/closing/year-end': { section: closing, icon: 'bx-calendar-event' }
}

export function getPageNavigation(path: string, title: string) {
  const trail = findMenuTrail(path, menu) ?? []
  const last = trail.at(-1)
  const outsideMenu = pagesOutsideMenu[path]
  const parents = last?.href ? trail : trail.slice(0, -1)

  if (outsideMenu?.section) parents.push(outsideMenu.section)

  const journalPageIcon =
    last?.href === '/accounting/transactions/journal-entries'
      ? journalPageIcons.get(path.split('/').pop() ?? '')
      : undefined

  const icon = outsideMenu?.icon ?? journalPageIcon ?? last?.icon ?? defaultIcon
  const currentPage = { label: last?.href ? title : (last?.label ?? title), icon }

  return {
    icon,
    breadcrumbs: [...(path === homeCrumb.href ? [] : [homeCrumb]), ...parents, currentPage]
  }
}
