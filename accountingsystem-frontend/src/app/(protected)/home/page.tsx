import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { serverCan } from '@/lib/server-access'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { DashboardHomePage } from '@/views/(protected)/dashboard/DashboardHomePage'

export default async function Page() {
  if (!(await serverCan(APP_ABILITIES.accountingRead))) {
    return (
      <AccessDeniedState
        title='Accounting dashboard'
        description='Your role needs accounting access to view financial reports.'
      />
    )
  }

  return <DashboardHomePage />
}
