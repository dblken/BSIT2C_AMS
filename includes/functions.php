<?php
function getDayName($dayNumber) {
    $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
        // Add string mappings
        'M' => 'Monday',
        'T' => 'Tuesday',
        'W' => 'Wednesday',
        'TH' => 'Thursday',
        'F' => 'Friday',
        'SAT' => 'Saturday',
        'SUN' => 'Sunday',
        // Already day names should return themselves
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday'
    ];
    
    // For case-insensitive matching with string keys
    if (is_string($dayNumber) && !is_numeric($dayNumber)) {
        foreach ($days as $key => $value) {
            if (strcasecmp($key, $dayNumber) === 0 || strcasecmp($value, $dayNumber) === 0) {
                return $value;
            }
        }
    }
    
    return $days[$dayNumber] ?? 'Unknown';
}

// Add or update this function to standardize the formatting of days across the application
function formatDays($days) {
    // If it's already a string and not JSON, return it as is
    if (is_string($days) && !isJson($days)) {
        return getDayName($days);
    }
    
    // If it's a JSON string, decode it
    if (is_string($days)) {
        $days = json_decode($days, true);
    }
    
    // If it's an array after decoding, format it
    if (is_array($days)) {
        // Convert day codes to day names if needed
        $day_names = array();
        foreach ($days as $day) {
            $day_names[] = getDayName($day);
        }
        return implode(', ', $day_names);
    }
    
    // Default fallback
    return $days;
}

// Helper function to check if a string is valid JSON
function isJson($string) {
    if (!is_string($string)) {
        return false;
    }
    
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// Add any other utility functions here
?> 