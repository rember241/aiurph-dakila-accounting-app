<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Login successful',
            'user' => [
                'id' => (string) $this->id,
                'name' => $this->name,
                'email' => $this->email,
            ],
        ];
    }
}
