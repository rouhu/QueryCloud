<?php
// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

require 'boot.php';

// Set a default timezone if not set in php.ini
date_default_timezone_set('UTC');

echo "Cron job started at " . date('Y-m-d H:i:s') . "\n";

/**
 * Checks if a scheduled ETL job is due to run based on its specific configuration.
 *
 * @param array $etl_config The decoded etl_config from the database.
 * @param DateTime $now The current time.
 * @return bool True if the job is due, false otherwise.
 */
function isJobDue($etl_config, DateTime $now) {
    $schedule_type = $etl_config['schedule_type'] ?? 'inactive';
    if ($schedule_type === 'inactive') {
        return false;
    }

    $last_run_at = null;
    if (isset($etl_config['last_run_at'])) {
        try {
            $last_run_at = new DateTime($etl_config['last_run_at']);
        } catch (Exception $e) {
            echo "Skipping job due to invalid last_run_at date format: " . $etl_config['last_run_at'] . "\n";
            return false;
        }
    }

    switch ($schedule_type) {
        case 'minutely':
            $interval = (int)($etl_config['schedule_interval'] ?? 5);
            if (!$last_run_at) return true; // First run
            $next_run_at = (clone $last_run_at)->add(new DateInterval("PT{$interval}M"));
            return $now >= $next_run_at;

        case 'hourly':
            $allowed_hours = $etl_config['schedule_hours'] ?? [];
            if (empty($allowed_hours)) return false;
            $current_hour = (int)$now->format('G'); // 0-23 format
            if (!in_array($current_hour, $allowed_hours)) {
                return false; // Not a scheduled hour
            }
            if (!$last_run_at) return true; // Never run before, and it's a valid hour
            // Check if the last run was in a different hour. This prevents multiple runs in the same hour.
            return $last_run_at->format('Y-m-d H') !== $now->format('Y-m-d H');

        case 'daily':
            $allowed_days = $etl_config['schedule_days'] ?? [];
            if (empty($allowed_days)) return false;
            $current_day = (int)$now->format('j'); // 1-31 format
             if (!in_array($current_day, $allowed_days)) {
                return false; // Not a scheduled day
            }
            // Check if time is past midnight (it always will be if cron runs after 00:00)
            if (!$last_run_at) return true; // Never run before, and it's a valid day
            // Check if the last run was on a different day. Prevents multiple runs on the same day.
            return $last_run_at->format('Y-m-d') !== $now->format('Y-m-d');

        case 'weekly':
            if (!$last_run_at) return true; // First run
            $next_run_at = (clone $last_run_at)->add(new DateInterval('P1W'));
            return $now >= $next_run_at;

        default:
            return false;
    }
}

// --- Main Execution Logic ---

$now = new DateTime();
$saved_queries = ORM::for_table('saved_queries')->where_not_null('etl_config')->find_many();

echo "Found " . count($saved_queries) . " queries with ETL config.\n";

foreach ($saved_queries as $saved_query) {
    $etl_config = json_decode($saved_query->etl_config, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($etl_config)) {
        echo "Skipping query ID {$saved_query->id} due to invalid ETL config JSON.\n";
        continue;
    }

    if (isJobDue($etl_config, $now)) {
        echo "Query ID {$saved_query->id} ('{$saved_query->query_name}') is due. Starting ETL process.\n";

        $log = ORM::for_table('etl_logs')->create();
        $log->saved_query_id = $saved_query->id;
        $log->execution_time = date('Y-m-d H:i:s');
        $log->status = 'running';
        $log->message = 'ETL process started.';
        $log->save();

        // Execute the refactored ETL job logic
        $result = ETL::executeEtlJob($saved_query);
        $current_execution_time = date('Y-m-d H:i:s');

        // Update the log with the result
        if ($result['status'] === 'success' || $result['status'] === 'info') {
            $log->status = 'success';
            // Update the 'last_run_at' timestamp ONLY on successful execution
            $etl_config['last_run_at'] = $current_execution_time;
            $saved_query->etl_config = json_encode($etl_config);
            $saved_query->save();
            echo "ETL for query ID {$saved_query->id} completed successfully.\n";
        } else { // 'error'
            $log->status = 'failed';
            echo "ETL for query ID {$saved_query->id} failed: " . $result['message'] . "\n";
        }

        $log->message = $result['message'];
        $log->ended_at = $current_execution_time;
        $log->save();

        echo "Finished processing for query ID {$saved_query->id}.\n\n";
    }
}

echo "Cron job finished at " . date('Y-m-d H:i:s') . "\n";
?>
