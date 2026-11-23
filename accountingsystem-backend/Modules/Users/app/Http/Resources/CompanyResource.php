<?php

namespace Modules\Users\Http\Resources;

/**
 * Company API resource.
 *
 * Company-domain alias over the legacy tenant resource payload.
 * New consumers should prefer this resource while legacy tenant endpoints can
 * continue to reuse the same underlying storage contract safely.
 *
 * @mixin \Modules\Users\Models\Company
 */
class CompanyResource extends TenantResource
{
}
