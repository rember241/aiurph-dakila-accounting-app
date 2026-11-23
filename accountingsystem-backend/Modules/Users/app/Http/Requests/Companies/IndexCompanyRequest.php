<?php

namespace Modules\Users\Http\Requests\Companies;

use Modules\Users\Http\Requests\Tenants\IndexTenantRequest;

/**
 * Company list filters.
 *
 * Compatibility wrapper over the legacy tenant filter request.
 */
final class IndexCompanyRequest extends IndexTenantRequest
{
}
