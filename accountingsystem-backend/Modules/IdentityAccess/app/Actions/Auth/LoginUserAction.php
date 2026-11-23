<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\IdentityAccess\Http\Requests\Auth\LoginRequest;
use Modules\Users\Enums\UserStatus;
use Modules\Users\Services\Users\UserOnboardingQueryService;

final class LoginUserAction
{
    public function __construct(
        private readonly UserOnboardingQueryService $userOnboardingQueryService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(LoginRequest $request): User
    {
        $this->ensureIsNotRateLimited($request);

        if (! Auth::guard('web')->attempt(
            $request->safe()->only(['email', 'password']),
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            Auth::guard('web')->logout();

            throw new LogicException('Authenticated user has an unexpected type.');
        }

        if ((string) ($user->status ?? UserStatus::ACTIVE->value) !== UserStatus::ACTIVE->value) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact an administrator.'],
            ])->status(403);
        }

        $companyAccessStatus = $this->userOnboardingQueryService->status($user);
        $hasAnyActiveAccess = $companyAccessStatus['has_owned_company']
            || $companyAccessStatus['has_active_company_membership'];

        if (! $hasAnyActiveAccess && $companyAccessStatus['has_any_company_membership']) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['Your company access is inactive. Please contact an administrator.'],
            ])->status(403);
        }

        $user->forceFill(['last_active_at' => now()])->save();

        if (! $request->hasSession()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'session' => [
                    'Login session could not be started. Open the ERP through the configured app URL and make sure it is included in SANCTUM_STATEFUL_DOMAINS.',
                ],
            ])->status(419);
        }

        $request->session()->regenerate();

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => [
                trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ],
        ]);
    }

    private function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        );
    }
}
