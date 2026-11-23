import { useEffect, useMemo, useState } from 'react'

import Accordion from '@mui/material/Accordion'
import AccordionDetails from '@mui/material/AccordionDetails'
import AccordionSummary from '@mui/material/AccordionSummary'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Menu from '@mui/material/Menu'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { SimpleTreeView, TreeItem } from '@mui/x-tree-view'

import {
  ORG_UNIT_TYPES,
  getOrgUnitStatusLabel,
  getOrgUnitTypeIcon,
  getOrgUnitTypeLabel
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

import type {
  OrgUnitRecord,
  OrgUnitTreeNode,
  OrgUnitType
} from '@/features/(protected)/users/company-structure/model/company-structure.types'

type OrgUnitHierarchyView = 'tree' | 'grouped'

type FlatOrgUnitNode = OrgUnitTreeNode & {
  parentName: string | null
}

type OrgUnitHierarchyPanelProps = {
  hierarchy: OrgUnitTreeNode[]
  selectedOrgUnitId: string | null
  canManage: boolean
  onSelect: (orgUnit: OrgUnitRecord) => void
}

const VIEW_OPTIONS: Array<{ value: OrgUnitHierarchyView; label: string }> = [
  { value: 'tree', label: 'Structure Tree' },
  { value: 'grouped', label: 'Grouped View' }
]

function flattenHierarchy(nodes: OrgUnitTreeNode[], parentName: string | null = null): FlatOrgUnitNode[] {
  return nodes.flatMap(node => {
    const currentNode: FlatOrgUnitNode = {
      ...node,
      parentName
    }

    return [currentNode, ...flattenHierarchy(node.children, node.name)]
  })
}

function collectExpandableIds(nodes: OrgUnitTreeNode[]): string[] {
  return nodes.flatMap(node => {
    if (node.children.length === 0) {
      return []
    }

    return [node.id, ...collectExpandableIds(node.children)]
  })
}

function TreeNodeLabel({ node }: { node: FlatOrgUnitNode }) {
  return (
    <Stack direction='row' spacing={1.5} alignItems='center' sx={{ minWidth: 0, py: 0.5 }}>
      <Box
        component='i'
        className={`bx ${getOrgUnitTypeIcon(node.type)}`}
        sx={{ color: 'text.secondary', fontSize: 18, flexShrink: 0 }}
      />

      <Box sx={{ minWidth: 0, flexGrow: 1 }}>
        <Typography
          variant='body2'
          fontWeight={600}
          noWrap
          sx={{ color: node.status === 'inactive' ? 'text.disabled' : 'text.primary' }}
        >
          {node.name}
        </Typography>

        <Typography variant='caption' color='text.secondary' noWrap>
          {getOrgUnitTypeLabel(node.type)}
          {node.parentName ? ` • Reports to ${node.parentName}` : ' • Company root'}
          {node.directChildrenCount > 0
            ? ` • ${node.directChildrenCount} sub-unit${node.directChildrenCount === 1 ? '' : 's'}`
            : ''}
        </Typography>
      </Box>

      {node.status === 'inactive' ? <Chip size='small' variant='outlined' label='Inactive' /> : null}
    </Stack>
  )
}

function renderTreeNode(node: OrgUnitTreeNode, flatNodeMap: Map<string, FlatOrgUnitNode>) {
  const flatNode = flatNodeMap.get(node.id)

  if (!flatNode) {
    return null
  }

  return (
    <TreeItem key={node.id} itemId={node.id} label={<TreeNodeLabel node={flatNode} />}>
      {node.children.map(child => renderTreeNode(child, flatNodeMap))}
    </TreeItem>
  )
}

function OrgUnitHierarchyPanel({ hierarchy, selectedOrgUnitId, canManage, onSelect }: OrgUnitHierarchyPanelProps) {
  const [view, setView] = useState<OrgUnitHierarchyView>('tree')
  const [menuAnchorEl, setMenuAnchorEl] = useState<HTMLElement | null>(null)
  const flatNodes = useMemo(() => flattenHierarchy(hierarchy), [hierarchy])
  const flatNodeMap = useMemo(() => new Map(flatNodes.map(node => [node.id, node])), [flatNodes])

  const groupedNodes = useMemo(() => {
    return ORG_UNIT_TYPES.reduce<Record<OrgUnitType, FlatOrgUnitNode[]>>(
      (groups, type) => {
        groups[type] = flatNodes.filter(node => node.type === type)

        return groups
      },
      {
        head_office: [],
        branch: [],
        department: [],
        team: []
      }
    )
  }, [flatNodes])

  const defaultExpandedItems = useMemo(() => collectExpandableIds(hierarchy), [hierarchy])
  const [expandedItems, setExpandedItems] = useState<string[]>(defaultExpandedItems)

  useEffect(() => {
    setExpandedItems(defaultExpandedItems)
  }, [defaultExpandedItems])

  const selectedNode = selectedOrgUnitId ? (flatNodeMap.get(selectedOrgUnitId) ?? null) : null
  const currentViewLabel = VIEW_OPTIONS.find(option => option.value === view)?.label ?? VIEW_OPTIONS[0].label

  const handleSelect = (orgUnitId: string) => {
    const node = flatNodeMap.get(orgUnitId)

    if (node) {
      onSelect(node)
    }
  }

  return (
    <Stack spacing={3} sx={{ flexGrow: 1, minHeight: 0, p: 4, height: '100%' }}>
      <Stack direction='row' spacing={2} justifyContent='space-between' alignItems='flex-start'>
        <Button
          variant='text'
          color='inherit'
          size='small'
          endIcon={<i className='bx bx-chevron-down' />}
          onClick={event => setMenuAnchorEl(event.currentTarget)}
          sx={{ flexShrink: 0, minWidth: 0, px: 0.5 }}
        >
          {currentViewLabel}
        </Button>
      </Stack>

      <Menu
        anchorEl={menuAnchorEl}
        open={!!menuAnchorEl}
        onClose={() => setMenuAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        {VIEW_OPTIONS.map(option => (
          <MenuItem
            key={option.value}
            selected={option.value === view}
            onClick={() => {
              setView(option.value)
              setMenuAnchorEl(null)
            }}
          >
            {option.label}
          </MenuItem>
        ))}
      </Menu>

      {selectedNode ? (
        <Box
          sx={{
            px: 2,
            py: 1.5,
            borderRadius: 2,
            bgcolor: 'action.hover',
            border: '1px solid',
            borderColor: 'divider'
          }}
        >
          <Typography variant='caption' color='text.secondary'>
            Current selection
          </Typography>
          <Typography variant='body2' fontWeight={700}>
            {selectedNode.name}
          </Typography>
          <Typography variant='caption' color='text.secondary'>
            {getOrgUnitTypeLabel(selectedNode.type)} • {getOrgUnitStatusLabel(selectedNode.status)}
          </Typography>
        </Box>
      ) : null}

      <Box sx={{ flexGrow: 1, minHeight: 0, overflowY: 'auto', pr: 0.5 }}>
        {hierarchy.length > 0 ? (
          view === 'tree' ? (
            <SimpleTreeView
              expandedItems={expandedItems}
              selectedItems={selectedOrgUnitId}
              onExpandedItemsChange={(_, itemIds) => setExpandedItems(itemIds)}
              onSelectedItemsChange={(_, itemId) => {
                if (typeof itemId === 'string') {
                  handleSelect(itemId)
                }
              }}
              expansionTrigger='iconContainer'
              sx={{
                '& .MuiTreeItem-content': {
                  borderRadius: 1.5,
                  pr: 1.25,
                  minHeight: 42
                },
                '& .MuiTreeItem-content:hover': {
                  bgcolor: 'action.hover'
                },
                '& .MuiTreeItem-content.Mui-selected, & .MuiTreeItem-content.Mui-selected:hover': {
                  bgcolor: 'action.selected'
                },
                '& .MuiTreeItem-label': {
                  width: '100%'
                }
              }}
            >
              {hierarchy.map(node => renderTreeNode(node, flatNodeMap))}
            </SimpleTreeView>
          ) : (
            <Stack spacing={1.5}>
              {ORG_UNIT_TYPES.map(type => {
                const nodes = groupedNodes[type]

                if (nodes.length === 0) {
                  return null
                }

                return (
                  <Accordion
                    key={type}
                    defaultExpanded={type === 'head_office' || type === 'branch'}
                    disableGutters
                    elevation={0}
                    sx={{
                      border: '1px solid',
                      borderColor: 'divider',
                      borderRadius: '12px !important',
                      bgcolor: 'background.default',
                      '&::before': {
                        display: 'none'
                      }
                    }}
                  >
                    <AccordionSummary expandIcon={<i className='bx bx-chevron-down' />}>
                      <Stack direction='row' spacing={1.25} alignItems='center' sx={{ minWidth: 0 }}>
                        <Box
                          component='i'
                          className={`bx ${getOrgUnitTypeIcon(type)}`}
                          sx={{ color: 'text.secondary', fontSize: 18 }}
                        />
                        <Typography variant='body2' fontWeight={700}>
                          {getOrgUnitTypeLabel(type)}
                        </Typography>
                        <Chip size='small' variant='tonal' color='primary' label={nodes.length} />
                      </Stack>
                    </AccordionSummary>

                    <AccordionDetails sx={{ pt: 0 }}>
                      <List disablePadding>
                        {nodes.map(node => (
                          <ListItemButton
                            key={node.id}
                            selected={node.id === selectedOrgUnitId}
                            onClick={() => handleSelect(node.id)}
                            sx={{
                              borderRadius: 2,
                              px: 1.5,
                              py: 1.25,
                              alignItems: 'flex-start',
                              mb: 0.75,
                              '&.Mui-selected': {
                                bgcolor: 'action.selected'
                              },
                              '&.Mui-selected:hover': {
                                bgcolor: 'action.selected'
                              }
                            }}
                          >
                            <ListItemText
                              primary={
                                <Stack direction='row' spacing={1.25} alignItems='center' sx={{ minWidth: 0 }}>
                                  <Typography variant='body2' fontWeight={600} noWrap>
                                    {node.name}
                                  </Typography>
                                  {node.status === 'inactive' ? (
                                    <Chip size='small' variant='outlined' label='Inactive' />
                                  ) : null}
                                </Stack>
                              }
                              secondary={
                                <Typography variant='caption' color='text.secondary' component='span'>
                                  {node.parentName ? `Reports to ${node.parentName}` : 'Company root'}
                                  {node.directChildrenCount > 0
                                    ? ` • ${node.directChildrenCount} sub-unit${node.directChildrenCount === 1 ? '' : 's'}`
                                    : ''}
                                </Typography>
                              }
                            />
                          </ListItemButton>
                        ))}
                      </List>
                    </AccordionDetails>
                  </Accordion>
                )
              })}
            </Stack>
          )
        ) : (
          <Typography variant='body2' color='text.secondary'>
            No org units have been created yet.
          </Typography>
        )}
      </Box>
    </Stack>
  )
}

export default OrgUnitHierarchyPanel
