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
 * Checks if a scheduled ETL job is due to run.
 *
 * @param array $etl_config The decoded etl_config from the database.
 * @return bool True if the job is due, false otherwise.
 */
function isJobDue($etl_config) {
    $schedule_type = $etl_config['schedule_type'] ?? 'inactive';
    if ($schedule_type === 'inactive') {
        return false;
    }

    $last_run_at_str = $etl_config['last_run_at'] ?? null;

    // If it has never been run, it's due now.
    if (!$last_run_at_str) {
        return true;
    }

    try {
        $last_run_at = new DateTime($last_run_at_str);
        $now = new DateTime();

        $next_run_at = clone $last_run_at;

        switch ($schedule_type) {
            case 'minutely':
                $interval_minutes = (int)($etl_config['schedule_interval'] ?? 5);
                $next_run_at->add(new DateInterval('PT' . $interval_minutes . 'M'));
                break;
            case 'hourly':
                $next_run_at->add(new DateInterval('PT1H'));
                break;
            case 'daily':
                $next_run_at->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $next_run_at->add(new DateInterval('P1W'));
                break;
            case 'monthly':
                $next_run_at->add(new DateInterval('P1M'));
                break;
            default:
                return false;
        }

        return $now >= $next_run_at;

    } catch (Exception $e) {
        // Log error if date parsing fails, and assume not due.
        echo "Error checking schedule for a job: " . $e->getMessage() . "\n";
        return false;
    }
}

// Fetch all saved queries that might have an ETL configuration.
$saved_queries = ORM::for_table('saved_queries')->where_not_null('etl_config')->find_many();

echo "Found " . count($saved_queries) . " queries with ETL config.\n";

foreach ($saved_queries as $saved_query) {
    $etl_config = json_decode($saved_query->etl_config, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($etl_config)) {
        echo "Skipping query ID {$saved_query->id} due to invalid ETL config JSON.\n";
        continue;
    }

    if (isJobDue($etl_config)) {
        echo "Query ID {$saved_query->id} ('{$saved_query->query_name}') is due. Starting ETL process.\n";

        $log = ORM::for_table('etl_logs')->create();
        $log->saved_query_id = $saved_query->id;
        $log->execution_time = date('Y-m-d H:i:s');
        $log->status = 'running';
        $log->message = 'ETL process started.';
        $log->save();

        // Execute the refactored ETL job logic
        $result = ETL::executeEtlJob($saved_query);

        // Update the log with the result
        if ($result['status'] === 'success' || $result['status'] === 'info') {
            $log->status = 'success';
            // Update the 'last_run_at' timestamp ONLY on successful execution
            $etl_config['last_run_at'] = date('Y-m-d H:i:s');
            $saved_query->etl_config = json_encode($etl_config);
            $saved_query->save();
             echo "ETL for query ID {$saved_query->id} completed successfully.\n";
        } else { // 'error'
            $log->status = 'failed';
            echo "ETL for query ID {$saved_query->id} failed: " . $result['message'] . "\n";
        }

        $log->message = $result['message'];
        $log->ended_at = date('Y-m-d H:i:s');
        $log->save();

        echo "Finished processing for query ID {$saved_query->id}.\n\n";
    }
}

echo "Cron job finished at " . date('Y-m-d H:i:s') . "\n";

?>
