<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Great-circle distance helpers that work on MySQL, PostgreSQL and SQLite
 * (SQLite gets the math functions registered in AppServiceProvider).
 */
class Geo
{
    public const EARTH_RADIUS_KM = 6371;

    /** Selects `table.*` plus a computed `distance` column in km. */
    public static function selectDistance(Builder $query, float $lat, float $lng, string $table): Builder
    {
        if (! $query->getQuery()->columns) {
            $query->select($table.'.*');
        }

        return $query->selectRaw(self::expression($query, $table).' AS distance', [$lat, $lng, $lat]);
    }

    /** Restricts to rows within the radius (bounding box first, then exact distance). */
    public static function restrictToRadius(Builder $query, float $lat, float $lng, float $radiusKm, string $table): Builder
    {
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / max(0.01, 111.045 * cos(deg2rad($lat)));

        return $query
            ->whereNotNull($table.'.latitude')
            ->whereNotNull($table.'.longitude')
            ->whereBetween($table.'.latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween($table.'.longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->whereRaw(self::expression($query, $table).' <= ?', [$lat, $lng, $lat, $radiusKm]);
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function expression(Builder $query, string $table): string
    {
        $clamp = $query->getConnection()->getDriverName() === 'sqlite' ? 'MIN' : 'LEAST';
        $lat = "{$table}.latitude";
        $lng = "{$table}.longitude";

        return '('.self::EARTH_RADIUS_KM." * acos({$clamp}(1, cos(radians(?)) * cos(radians({$lat})) * cos(radians({$lng}) - radians(?)) + sin(radians(?)) * sin(radians({$lat})))))";
    }
}
