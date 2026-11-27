<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SqlQueryService
{
    /**
     * Execute a SQL query from a file
     *
     * @param  string  $filename  The SQL filename (without extension)
     * @param  array  $parameters  Parameters to bind to the query
     * @return Collection
     */
    public function executeQueryFromFile(string $filename, array $parameters = [])
    {
        $sqlPath = database_path("sql/{$filename}.sql");

        if (! File::exists($sqlPath)) {
            throw new \Exception("SQL file not found: {$sqlPath}");
        }

        $sql = File::get($sqlPath);

        // Remove comments and clean up the SQL
        $sql = $this->cleanSql($sql);

        // Execute the query with parameters using gomedisys connection
        return DB::connection('gomedisys')->select($sql, $parameters);
    }

    /**
     * Execute a SQL query and return first result
     *
     * @param  string  $filename  The SQL filename (without extension)
     * @param  array  $parameters  Parameters to bind to the query
     * @return object|null
     */
    public function executeQueryFromFileFirst(string $filename, array $parameters = [])
    {
        $results = $this->executeQueryFromFile($filename, $parameters);

        // Handle both Laravel collections and arrays
        if (is_array($results)) {
            return ! empty($results) ? (object) $results[0] : null;
        }

        return $results->first();
    }

    /**
     * Clean SQL by removing comments and extra whitespace
     */
    private function cleanSql(string $sql): string
    {
        // Remove SQL comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Remove extra whitespace and newlines
        $sql = preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }

    /**
     * Get the raw SQL content from file (for debugging)
     */
    public function getSqlContent(string $filename): string
    {
        $sqlPath = database_path("sql/{$filename}.sql");

        if (! File::exists($sqlPath)) {
            throw new \Exception("SQL file not found: {$sqlPath}");
        }

        return File::get($sqlPath);
    }
}
