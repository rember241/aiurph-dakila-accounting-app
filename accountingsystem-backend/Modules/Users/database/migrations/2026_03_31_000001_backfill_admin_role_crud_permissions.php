<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->select(['id', 'key', 'system_generated', 'module_permissions'])
            ->where('system_generated', true)
            ->whereRaw('LOWER(key) = ?', ['admin'])
            ->orderBy('id')
            ->get()
            ->each(function (object $role): void {
                $modulePermissions = json_decode((string) $role->module_permissions, true);

                if (!is_array($modulePermissions)) {
                    return;
                }

                $updated = false;

                foreach ($modulePermissions as $module => $verbs) {
                    if (!is_array($verbs)) {
                        continue;
                    }

                    $normalizedVerbs = [];

                    foreach ($verbs as $verb) {
                        if (!is_string($verb)) {
                            continue;
                        }

                        $normalizedVerb = strtolower(trim($verb));
                        if ($normalizedVerb === '') {
                            continue;
                        }

                        $normalizedVerbs[$normalizedVerb] = $normalizedVerb;
                    }

                    if (!isset($normalizedVerbs['write'])) {
                        continue;
                    }

                    foreach (['create', 'delete'] as $crudVerb) {
                        if (!isset($normalizedVerbs[$crudVerb])) {
                            $normalizedVerbs[$crudVerb] = $crudVerb;
                            $updated = true;
                        }
                    }

                    $modulePermissions[$module] = array_values($normalizedVerbs);
                }

                if (!$updated) {
                    return;
                }

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
        // This backfill preserves upgraded CRUD permissions and is intentionally not reversed automatically.
    }
};
