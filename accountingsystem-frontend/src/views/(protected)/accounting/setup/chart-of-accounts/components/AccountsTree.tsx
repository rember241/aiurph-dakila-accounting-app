import React from 'react'

import Box from '@mui/material/Box'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import IconButton from '@mui/material/IconButton'
import Tooltip from '@mui/material/Tooltip'
import Skeleton from '@mui/material/Skeleton'
import { SimpleTreeView, TreeItem } from '@mui/x-tree-view'

import type { AccountTreeNode } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

type AccountsTreeProps = {
  nodes: AccountTreeNode[]
  loading?: boolean
  onView: (account: AccountTreeNode) => void
  onEdit: (account: AccountTreeNode) => void
  onDelete: (account: AccountTreeNode) => void
  canEditAccounts?: boolean
  canDeleteAccounts?: boolean
}

type UiTreeNode = {
  id: string
  label: string
  account: AccountTreeNode
  children: UiTreeNode[]
}

function buildUiNode(account: AccountTreeNode): UiTreeNode {
  return {
    id: account.id,
    label: `${account.code} - ${account.name}`,
    account,
    children: (account.children ?? []).map(buildUiNode)
  }
}

function collectExpandableIds(nodes: UiTreeNode[]): string[] {
  return nodes.flatMap(node => {
    if (!node.children.length) return []

    return [node.id, ...collectExpandableIds(node.children)]
  })
}

function NodeLabel({
  node,
  isExpanded,
  onView,
  onEdit,
  onDelete,
  canEditAccounts,
  canDeleteAccounts
}: {
  node: UiTreeNode
  isExpanded: boolean
  onView: (account: AccountTreeNode) => void
  onEdit: (account: AccountTreeNode) => void
  onDelete: (account: AccountTreeNode) => void
  canEditAccounts?: boolean
  canDeleteAccounts?: boolean
}) {
  const account = node.account
  const isSystem = account.is_system
  const isFolder = node.children.length > 0 || !account.is_posting
  const isOpenFolder = isFolder && node.children.length > 0 && isExpanded

  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, py: 0.4, minWidth: 0 }}>
      <Box sx={{ width: 20, textAlign: 'center', color: 'text.secondary', position: 'relative', height: 18 }}>
        {isFolder ? (
          <>
            <i
              className='bx bx-folder'
              style={{
                fontSize: 18,
                position: 'absolute',
                left: 0,
                top: 0,
                opacity: isOpenFolder ? 0 : 1,
                transform: isOpenFolder ? 'scale(0.86) translateY(1px)' : 'scale(1)',
                transition: 'opacity 0.2s ease, transform 0.2s ease'
              }}
            />
            <i
              className='bx bx-folder-open'
              style={{
                fontSize: 18,
                position: 'absolute',
                left: 0,
                top: 0,
                opacity: isOpenFolder ? 1 : 0,
                transform: isOpenFolder ? 'scale(1) translateY(0)' : 'scale(0.86) translateY(-1px)',
                transition: 'opacity 0.2s ease, transform 0.2s ease'
              }}
            />
          </>
        ) : (
          <i className='bx bx-radio-circle' style={{ fontSize: 18 }} />
        )}
      </Box>

      <Typography
        variant='body1'
        sx={{ flexGrow: 1, color: account.status === 'inactive' ? 'text.disabled' : 'text.primary' }}
      >
        {node.label}
      </Typography>

      <Stack
        direction='row'
        spacing={0.5}
        className='coa-row-actions'
        sx={{ opacity: 0, transition: 'opacity 0.15s ease', ml: 1 }}
      >
        <Tooltip title='View'>
          <IconButton
            size='small'
            onClick={event => {
              event.stopPropagation()
              onView(account)
            }}
          >
            <i className='bx bx-show' />
          </IconButton>
        </Tooltip>
        {canEditAccounts || canDeleteAccounts ? (
          <>
            {canEditAccounts ? (
              <Tooltip title={isSystem ? 'System account cannot be edited' : 'Edit'}>
                <span>
                  <IconButton
                    size='small'
                    onClick={event => {
                      event.stopPropagation()
                      onEdit(account)
                    }}
                    disabled={isSystem}
                  >
                    <i className='bx bx-pencil' />
                  </IconButton>
                </span>
              </Tooltip>
            ) : null}
            {canDeleteAccounts ? (
              <Tooltip title={isSystem ? 'System account cannot be deleted' : 'Delete'}>
                <span>
                  <IconButton
                    size='small'
                    onClick={event => {
                      event.stopPropagation()
                      onDelete(account)
                    }}
                    disabled={isSystem}
                  >
                    <i className='bx bx-trash' />
                  </IconButton>
                </span>
              </Tooltip>
            ) : null}
          </>
        ) : null}
      </Stack>
    </Box>
  )
}

function renderNode(
  node: UiTreeNode,
  expandedSet: Set<string>,
  onView: (account: AccountTreeNode) => void,
  onEdit: (account: AccountTreeNode) => void,
  onDelete: (account: AccountTreeNode) => void,
  canEditAccounts?: boolean,
  canDeleteAccounts?: boolean
) {
  return (
    <TreeItem
      key={node.id}
      itemId={node.id}
      label={
        <NodeLabel
          node={node}
          isExpanded={expandedSet.has(node.id)}
          onView={onView}
          onEdit={onEdit}
          onDelete={onDelete}
          canEditAccounts={canEditAccounts}
          canDeleteAccounts={canDeleteAccounts}
        />
      }
    >
      {node.children.map(child =>
        renderNode(child, expandedSet, onView, onEdit, onDelete, canEditAccounts, canDeleteAccounts)
      )}
    </TreeItem>
  )
}

function AccountsTree({
  nodes,
  loading,
  onView,
  onEdit,
  onDelete,
  canEditAccounts = true,
  canDeleteAccounts = true
}: AccountsTreeProps) {
  const uiNodes = React.useMemo(() => nodes.map(buildUiNode), [nodes])
  const expandedIds = React.useMemo(() => collectExpandableIds(uiNodes), [uiNodes])
  const [expandedItems, setExpandedItems] = React.useState<string[]>(expandedIds)

  React.useEffect(() => {
    setExpandedItems(expandedIds)
  }, [expandedIds])

  const expandedSet = React.useMemo(() => new Set(expandedItems), [expandedItems])

  if (loading) {
    return (
      <Paper sx={{ p: 5 }}>
        <Skeleton />
      </Paper>
    )
  }

  return (
    <Paper sx={{ p: 5 }}>
      <Stack spacing={3}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
          <Typography variant='h5'>Chart of Accounts Hierarchy</Typography>
        </Box>

        {uiNodes.length === 0 ? (
          <Typography variant='body2' color='text.secondary'>
            No accounts found.
          </Typography>
        ) : (
          <SimpleTreeView
            expandedItems={expandedItems}
            onExpandedItemsChange={(_, itemIds) => setExpandedItems(itemIds)}
            disableSelection
            sx={{
              '& .MuiTreeItem-content:hover .coa-row-actions': { opacity: 1 },
              '& .MuiTreeItem-label': { width: '100%' }
            }}
          >
            {uiNodes.map(node =>
              renderNode(node, expandedSet, onView, onEdit, onDelete, canEditAccounts, canDeleteAccounts)
            )}
          </SimpleTreeView>
        )}
      </Stack>
    </Paper>
  )
}

export default AccountsTree
