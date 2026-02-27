<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrWorkLocation extends Model
{
    protected $table = 'hr_work_locations';

    /**
     * IMPORTANT:
     * Keep this model aligned with the actual hr_work_locations table schema
     * created by the HR migrations (address, pincode, geofence_radius_meters).
     */
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geofence_radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employees(): HasMany
    {
        // FK in hr_employees table is `work_location_id`
        return $this->hasMany(HrEmployee::class, 'work_location_id');
    }

    // ==================== ACCESSORS ====================

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->pincode,
        ]);

        return implode(', ', $parts);
    }

    public function getCoordinatesAttribute(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return "{$this->latitude}, {$this->longitude}";
        }

        return null;
    }

    // ==================== METHODS ====================

    /**
     * Check if a point is within geofence radius (meters).
     *
     * If geofence is not configured (missing coords or radius), we allow by default.
     */
    public function isWithinGeofence(float $latitude, float $longitude): bool
    {
        if ($this->latitude === null || $this->longitude === null) {
            return true; // not configured
        }

        $radius = (int) ($this->geofence_radius_meters ?? 0);
        if ($radius <= 0) {
            return true; // not configured / disabled
        }

        $distance = $this->calculateDistance($latitude, $longitude);
        return $distance <= $radius;
    }

    /**
     * Calculate distance between two points in meters using Haversine formula.
     */
    public function calculateDistance(float $latitude, float $longitude): float
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad((float) $this->latitude);
        $lonFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
