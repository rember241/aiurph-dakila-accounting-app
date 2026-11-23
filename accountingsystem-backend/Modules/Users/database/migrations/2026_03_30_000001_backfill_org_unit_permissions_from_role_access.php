<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->select(['id', 'module_permissions'])
            ->orderBy('id')
            ->get()
            ->each(function (object $role): void {
                $modulePermissions = json_decode((string) $role->module_permissions, true);

                if (!is_array($modulePermissions) || array_key_exists('org_units', $modulePermissions)) {
                    return;
                }

                $derivedVerbs = [];

                foreach (['users', 'roles'] as $legacyModule) {
                    $verbs = $modulePermissions[$legacyModule] ?? null;

                    if (!is_array($verbs)) {
                        continue;
                    }

                    foreach ($verbs as $verb) {
                        if (!is_string($verb)) {
                            continue;
                        }

                        $normalizedVerb = strtolower(trim($verb));

                        if ($normalizedVerb === '') {
                            continue;
                        }

                        $derivedVerbs[$normalizedVerb] = $normalizedVerb;
                    }
                }

                if ($derivedVerbs === []) {
                    return;
                }

                $modulePermissions['org_units'] = array_values($derivedVerbs);
                ksort($modulePermissions);

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'module_permissions' => json_encode($modulePermissions, JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        // This backfill preserves access data and is intentionally not reversed automatically.
    }
};
