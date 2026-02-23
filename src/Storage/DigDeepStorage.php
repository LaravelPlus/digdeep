<?php

namespace LaravelPlus\DigDeep\Storage;

use PDO;

class DigDeepStorage
{
    private PDO $pdo;

    public function __construct(private string $path, private int $maxProfiles = 100)
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:'.$this->path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS profiles (
                id TEXT PRIMARY KEY,
                method TEXT NOT NULL,
                url TEXT NOT NULL,
                status_code INTEGER,
                duration_ms REAL,
                memory_peak_mb REAL,
                query_count INTEGER DEFAULT 0,
                query_time_ms REAL DEFAULT 0,
                is_ajax INTEGER DEFAULT 0,
                data TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS route_visits (
                url TEXT NOT NULL,
                method TEXT NOT NULL DEFAULT \'GET\',
                visit_count INTEGER DEFAULT 1,
                last_visited_at TEXT NOT NULL,
                PRIMARY KEY (url, method)
            )
        ');

        // Add is_ajax column if it doesn't exist (migration for existing DBs)
        try {
            $this->pdo->exec('ALTER TABLE profiles ADD COLUMN is_ajax INTEGER DEFAULT 0');
        } catch (\PDOException) {
            // Column already exists
        }
    }

    public function store(string $id, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO profiles (id, method, url, status_code, duration_ms, memory_peak_mb, query_count, query_time_ms, is_ajax, data, created_at)
            VALUES (:id, :method, :url, :status_code, :duration_ms, :memory_peak_mb, :query_count, :query_time_ms, :is_ajax, :data, :created_at)
        ');

        $performance = $data['performance'] ?? [];

        $stmt->execute([
            'id' => $id,
            'method' => $data['request']['method'] ?? 'GET',
            'url' => $data['request']['url'] ?? '',
            'status_code' => $data['response']['status_code'] ?? 0,
            'duration_ms' => $performance['duration_ms'] ?? 0,
            'memory_peak_mb' => $performance['memory_peak_mb'] ?? 0,
            'query_count' => $performance['query_count'] ?? 0,
            'query_time_ms' => $performance['query_time_ms'] ?? 0,
            'is_ajax' => ($data['is_ajax'] ?? false) ? 1 : 0,
            'data' => json_encode($data),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->incrementRouteVisit(
            $data['request']['url'] ?? '',
            $data['request']['method'] ?? 'GET',
        );

        $this->prune();
    }

    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return null;
        }

        $row['data'] = json_decode($row['data'], true);

        return $row;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, method, url, status_code, duration_ms, memory_peak_mb, query_count, query_time_ms, is_ajax, created_at
            FROM profiles
            ORDER BY created_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function prune(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn();

        if ($count > $this->maxProfiles) {
            $deleteCount = $count - $this->maxProfiles;
            $this->pdo->exec("
                DELETE FROM profiles WHERE id IN (
                    SELECT id FROM profiles ORDER BY created_at ASC LIMIT {$deleteCount}
                )
            ");
        }
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function stats(): array
    {
        $row = $this->pdo->query('
            SELECT
                COUNT(*) as total,
                COALESCE(AVG(duration_ms), 0) as avg_duration,
                COALESCE(MIN(duration_ms), 0) as min_duration,
                COALESCE(MAX(duration_ms), 0) as max_duration,
                COALESCE(AVG(query_count), 0) as avg_queries,
                COALESCE(AVG(memory_peak_mb), 0) as avg_memory,
                COALESCE(MAX(duration_ms), 0) as slowest_duration,
                COALESCE(MAX(query_count), 0) as most_queries
            FROM profiles
        ')->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'total' => 0,
            'avg_duration' => 0,
            'min_duration' => 0,
            'max_duration' => 0,
            'avg_queries' => 0,
            'avg_memory' => 0,
            'slowest_duration' => 0,
            'most_queries' => 0,
        ];
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM profiles');
    }

    public function incrementRouteVisit(string $url, string $method = 'GET'): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO route_visits (url, method, visit_count, last_visited_at)
            VALUES (:url, :method, 1, :now)
            ON CONFLICT(url, method) DO UPDATE SET
                visit_count = visit_count + 1,
                last_visited_at = :now2
        ');

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'url' => $url,
            'method' => $method,
            'now' => $now,
            'now2' => $now,
        ]);
    }

    /** @return array<int, array{url: string, method: string, visit_count: int, last_visited_at: string}> */
    public function topRoutes(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT url, method, visit_count, last_visited_at
            FROM route_visits
            ORDER BY visit_count DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
