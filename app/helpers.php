<?php

if (!function_exists('permission_check')) {
    function permission_check(string $code): bool
    {
        return app(\App\Services\PermissionService::class)->can(\Illuminate\Support\Facades\Auth::id(), $code);
    }
}
