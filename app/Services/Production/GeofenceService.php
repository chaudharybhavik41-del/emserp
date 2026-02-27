<?php

namespace App\Services\Production;

use App\Models\Hr\HrWorkLocation;

/**
 * WP-06: Server-side geofence evaluation for Production DPR submissions.
 *
 * IMPORTANT:
 * The current DB schema (per migrations) does NOT have these columns:
 * - hr_work_locations.is_geofence_enabled
 * - hr_work_locations.is_headquarters
 * - hr_work_locations.geofence_radius
 *
 * Instead it has:
 * - latitude
 * - longitude
 * - geofence_radius_meters
 * - is_active
 *
 * So this service resolves a geofence location using those existing columns.
 */
class GeofenceService
{
    /**
     * Evaluate a GPS point against the configured plant geofence.
     *
     * @param float|int|string|null $latitude
     * @param float|int|string|null $longitude
     * @return array{enabled: bool, status: string, distance_m: (float|null), location_id: (int|null)}
     */
    public function evaluate($latitude, $longitude): array
    {
        $lat = $this->toFloatOrNull($latitude);
        $lng = $this->toFloatOrNull($longitude);

        // -------------------------------------------------------
        // Resolve the active geofence location
        // -------------------------------------------------------
        // Preferred: EMS Infra HQ location (code EMS-HQ) if present.
        // Fallback: latest active location which has coordinates and a radius.
        $location = HrWorkLocation::query()
            ->where('is_active', true)
            ->where('code', 'EMS-HQ')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('geofence_radius_meters', '>', 0)
            ->first();

        if (! $location) {
            $location = HrWorkLocation::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('geofence_radius_meters', '>', 0)
                ->orderByDesc('id')
                ->first();
        }

        if (! $location) {
            return [
                'enabled' => false,
                'status' => 'not_configured',
                'distance_m' => null,
                'location_id' => null,
            ];
        }

        // Geofence configured, but GPS missing
        if ($lat === null || $lng === null) {
            return [
                'enabled' => true,
                'status' => 'unknown',
                'distance_m' => null,
                'location_id' => (int) $location->id,
            ];
        }

        // Calculate distance for messaging (and check inside)
        $distance = $location->calculateDistance($lat, $lng);
        $inside = $location->isWithinGeofence($lat, $lng);

        return [
            'enabled' => true,
            'status' => $inside ? 'inside' : 'outside',
            'distance_m' => $distance,
            'location_id' => (int) $location->id,
        ];
    }

    protected function toFloatOrNull($v): ?float
    {
        if ($v === null) {
            return null;
        }

        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return null;
            }
        }

        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }
}
