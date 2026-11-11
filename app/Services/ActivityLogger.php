<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Port of logActivity() from includes/config.php.
 *
 * The legacy helper swallowed its own exceptions and only called error_log(),
 * so an audit-write failure never broke the request. That behaviour is kept.
 *
 * `resource_type` must be one of the legacy ENUM values:
 * file | folder | user | announcement | department | system
 */
class ActivityLogger
{
    public function log(
        ?int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?string $description = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        try {
            ActivityLog::query()->create([
                'user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'description' => $description,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Never let an audit failure break the request (legacy behaviour).
            Log::error('Error logging activity: '.$e->getMessage());
        }
    }

    /**
     * Convenience wrapper that pulls IP / user-agent off the current request.
     */
    public function logRequest(
        ?int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?string $description = null,
        ?array $metadata = null,
    ): void {
        $this->log(
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $description,
            $metadata,
            request()->ip(),
            request()->userAgent(),
        );
    }
}