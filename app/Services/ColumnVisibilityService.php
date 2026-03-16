<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ColumnVisibilityService
{
    private const CACHE_KEY = 'column_visibility';
    private const STORAGE_PATH = 'column_visibility.json';

    /**
     * Get column visibility for FBA
     */
    public static function getFbaColumnVisibility()
    {
        return self::getColumnVisibility('fba');
    }

    /**
     * Set column visibility for FBA
     */
    public static function setFbaColumnVisibility(array $visibility)
    {
        return self::setColumnVisibility('fba', $visibility);
    }

    /**
     * Get column visibility for a specific page
     */
    public static function getColumnVisibility(string $page)
    {
        $allVisibility = self::getAllColumnVisibility();
        return $allVisibility[$page] ?? self::getDefaultVisibility($page);
    }

    /**
     * Set column visibility for a specific page
     */
    public static function setColumnVisibility(string $page, array $visibility)
    {
        $allVisibility = self::getAllColumnVisibility();
        $allVisibility[$page] = $visibility;
        self::saveAllColumnVisibility($allVisibility);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all column visibility data
     */
    private static function getAllColumnVisibility()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (Storage::exists(self::STORAGE_PATH)) {
                return json_decode(Storage::get(self::STORAGE_PATH), true) ?? [];
            }
            return [];
        });
    }

    /**
     * Save all column visibility data
     */
    private static function saveAllColumnVisibility(array $data)
    {
        Storage::put(self::STORAGE_PATH, json_encode($data));
        Cache::put(self::CACHE_KEY, $data, now()->addDays(30)); // Cache for 30 days
    }

    /**
     * Get default visibility for a page
     */
    private static function getDefaultVisibility(string $page)
    {
        if ($page === 'fba') {
            return [
                'Parent' => false, // Hidden by default
                'SKU' => false,    // Hidden by default
                // Add other columns as visible by default - you can add more defaults here
            ];
        }

        return [];
    }
}