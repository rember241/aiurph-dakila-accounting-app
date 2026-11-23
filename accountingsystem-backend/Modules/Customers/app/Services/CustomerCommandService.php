<?php

namespace Modules\Customers\Services;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;
use Throwable;

/**
 * Handles company-scoped customer write operations.
 */
final class CustomerCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentCompanyContext $currentCompanyContext,
        private readonly CustomerCodeNumberingService $customerCodeNumberingService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function create(array $payload): Customer
    {
        $companyId = $this->currentCompanyContext->companyId();
        $normalizedPayload = $this->normalizePayload($payload);

        $customer = DB::transaction(function () use ($companyId, $normalizedPayload): Customer {
            $code = $this->resolveCustomerCode($companyId, $normalizedPayload);

            return Customer::query()->create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => (string) $normalizedPayload['name'],
                'customer_group' => $normalizedPayload['customer_group'],
                'email' => $normalizedPayload['email'],
                'phone' => $normalizedPayload['phone'],
                'address' => $normalizedPayload['address'],
                'latitude' => $normalizedPayload['latitude'] ?? null,
                'longitude' => $normalizedPayload['longitude'] ?? null,
                'status' => (string) ($normalizedPayload['status'] ?? 'active'),
            ]);
        });

        $this->auditLogService->recordCompanyScoped(
            'customer.created',
            $customer,
            null,
            $this->snapshot($customer),
            [
                'company_id' => (string) $customer->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $customer;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function update(Customer $customer, array $payload): Customer
    {
        $this->ensureCurrentCompanyScope($customer);
        $normalizedPayload = $this->normalizePayload($payload, true);
        $before = $this->snapshot($customer);

        $updatedCustomer = DB::transaction(function () use ($customer, $normalizedPayload): Customer {
            $customer->fill([
                'name' => array_key_exists('name', $normalizedPayload) ? (string) $normalizedPayload['name'] : $customer->name,
                'customer_group' => array_key_exists('customer_group', $normalizedPayload) ? $normalizedPayload['customer_group'] : $customer->customer_group,
                'email' => array_key_exists('email', $normalizedPayload) ? $normalizedPayload['email'] : $customer->email,
                'phone' => array_key_exists('phone', $normalizedPayload) ? $normalizedPayload['phone'] : $customer->phone,
                'address' => array_key_exists('address', $normalizedPayload) ? $normalizedPayload['address'] : $customer->address,
                'latitude' => array_key_exists('latitude', $normalizedPayload) ? $normalizedPayload['latitude'] : $customer->latitude,
                'longitude' => array_key_exists('longitude', $normalizedPayload) ? $normalizedPayload['longitude'] : $customer->longitude,
                'status' => array_key_exists('status', $normalizedPayload) ? (string) $normalizedPayload['status'] : $customer->status,
            ]);

            if ($customer->isDirty()) {
                $customer->save();
            }

            return $customer->fresh();
        });

        if ($before !== $this->snapshot($updatedCustomer)) {
            $this->auditLogService->recordCompanyScoped(
                'customer.updated',
                $updatedCustomer,
                $before,
                $this->snapshot($updatedCustomer),
                [
                    'company_id' => (string) $updatedCustomer->company_id,
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );
        }

        return $updatedCustomer;
    }

    /**
     * @throws Throwable
     */
    public function transition(Customer $customer, string $status): Customer
    {
        $this->ensureCurrentCompanyScope($customer);

        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus === '' || $normalizedStatus === (string) $customer->status) {
            return $customer->fresh() ?? $customer;
        }

        $before = ['status' => (string) $customer->status];

        $updatedCustomer = DB::transaction(function () use ($customer, $normalizedStatus): Customer {
            $customer->update(['status' => $normalizedStatus]);

            return $customer->fresh();
        });

        $event = $normalizedStatus === 'active'
            ? 'customer.activated'
            : 'customer.deactivated';

        $this->auditLogService->recordCompanyScoped(
            $event,
            $updatedCustomer,
            $before,
            ['status' => (string) $updatedCustomer->status],
            [
                'company_id' => (string) $updatedCustomer->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedCustomer;
    }

    /**
     * @throws Throwable
     */
    public function delete(Customer $customer): void
    {
        $this->ensureCurrentCompanyScope($customer);

        DB::transaction(function () use ($customer): void {
            $before = $this->snapshot($customer);

            $this->auditLogService->recordCompanyScoped(
                'customer.deleted',
                $customer,
                $before,
                [],
                [
                    'company_id' => (string) $customer->company_id,
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );

            $customer->delete();
        });
    }

    private function ensureCurrentCompanyScope(Customer $customer): void
    {
        $companyId = $this->currentCompanyContext->companyId();

        if ((string) $customer->company_id === $companyId) {
            return;
        }

        abort(404, 'Customer not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Customer $customer): array
    {
        return [
            'id' => (string) $customer->getKey(),
            'company_id' => (string) $customer->company_id,
            'code' => (string) $customer->code,
            'name' => (string) $customer->name,
            'customer_group' => $customer->customer_group,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'status' => (string) $customer->status,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, bool $partial = false): array
    {
        $normalized = $payload;

        if (!$partial || array_key_exists('email', $payload)) {
            $normalized['email'] = array_key_exists('email', $payload) && $payload['email'] !== null
                ? mb_strtolower(trim((string) $payload['email']))
                : null;
        }

        if (!$partial || array_key_exists('phone', $payload)) {
            $normalized['phone'] = array_key_exists('phone', $payload) && $payload['phone'] !== null
                ? trim((string) $payload['phone'])
                : null;
        }

        if (!$partial || array_key_exists('address', $payload)) {
            $normalized['address'] = array_key_exists('address', $payload) && $payload['address'] !== null
                ? trim((string) $payload['address'])
                : null;
        }

        if (!$partial || array_key_exists('customer_group', $payload)) {
            $normalized['customer_group'] = array_key_exists('customer_group', $payload) && $payload['customer_group'] !== null
                ? strtolower(trim((string) $payload['customer_group']))
                : null;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveCustomerCode(string $companyId, array $payload): string
    {
        $providedCode = array_key_exists('code', $payload) && is_string($payload['code'])
            ? trim($payload['code'])
            : '';

        if ($providedCode !== '') {
            $this->customerCodeNumberingService->syncProvidedCode($companyId, $providedCode);

            return $providedCode;
        }

        return $this->customerCodeNumberingService->allocate($companyId);
    }
}
