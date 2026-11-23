<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * Account model
 *
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 * @property bool $is_posting
 * @property string $status
 * @property string $normal_balance
 * @property bool $is_contra
 * @property string|null $description
 * @property string $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounts\Account|null $parent
 * @method static \Database\Factories\AccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereIsContra($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereIsPosting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereNormalBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Accounts\Account whereUpdatedAt($value)
 */
	class Account extends \Eloquent {}
}

namespace App\Models{
/**
 * AuditLog model
 *
 * This model represents the audit logs of changes made to the application.
 *
 * @package App\Models
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 */
	class AuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

