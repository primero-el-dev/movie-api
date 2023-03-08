<?php

namespace App\Database;

use PDO;
use PDOStatement;

class Database
{
    public const PDO_ERROR_CODE_NONE = '00000';
    public static PDO $db;

    public static function init(): void
    {
        self::$db = self::getInstance();
    }

    public static function getInstance(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s',
                getenv('MYSQL_HOST'),
                getenv('MYSQL_PORT'),
                getenv('MYSQL_DATABASE')
            ),
            getenv('MYSQL_USER'),
            getenv('MYSQL_PASSWORD'),
            $options
        );
    }

    public static function wasCorrect(PDO $db = null): bool
    {
        $db ??= self::$db ??= self::getInstance();

        return $db->errorCode() === self::PDO_ERROR_CODE_NONE;
    }

    public static function execute(string $query, array $params = [], PDO $db = null): PDOStatement
    {
        $db ??= self::$db ??= self::getInstance();
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        return $stmt;
    }

    public static function select(string $query, array $params = [], PDO $db = null): array|false
    {
        return self::execute(...func_get_args())->fetch();
    }

    public static function selectAll(string $query, array $params = [], PDO $db = null): array|false
    {
        return self::execute(...func_get_args())->fetchAll();
    }

    public static function buildQuery(array $data): array
    {
        $query = '';
        $bindings = $data['bindings'] ?? [];

        if ($data['type'] == 'SELECT') {
            $query = 'SELECT ' . implode(',', $data['values']) . ' FROM ' . $data['table'];

            if (!empty($data['joins'])) {
                $query .= ' ' . implode(' ', $data['joins']);
            }

            if (!empty($data['where'])) {
                $query .= ' WHERE ' . self::parseWhere($data['where']);
            }

            if (isset($data['order'])) {
                $query .= ' ORDER BY ' . implode(',', $data['order']);
            }

            if (isset($data['limit'])) {
                $query .= ' LIMIT ' . $data['limit'];
            }
        }
        elseif ($data['type'] == 'INSERT') {
            $query = sprintf(
                'INSERT INTO %s (%s) VALUES ',
                $data['table'],
                implode(',', array_keys($data['values']))
            );

            if (array_is_list($data['values'])) {
                $result = [];
                $i = 1;
                foreach ($data['values'] as $values) {
                    $keys = array_map(fn($k) => sprintf(':%s_%d', $k, $i), array_keys($values));
                    $result[] = '(' . implode(',', $keys) . ')';
                    $bindings += array_combine($keys, array_values($values));
                    $i++;
                }
                $query .= implode(',', $result);
            }
            else {
                $keys = array_map(fn($k) => ":$k", array_keys($data['values']));
                $query .= '(' . implode(',', $keys) . ')';
                $bindings += array_combine($keys, array_values($data['values']));
            }
        }
        elseif ($data['type'] == 'UPDATE') {
            $i = 1;
            $set = [];
            foreach ($data['values'] as $key => $value) {
                $k = sprintf(':%s_%d', $key, $i++);
                $bindings[$k] = $value;
                $set[] = "$key = $k";
            }

            $query = sprintf(
                'UPDATE %s SET %s',
                $data['table'],
                implode(',', $set),
            );

            if (!empty($data['where'])) {
                $query .= ' WHERE ' . self::parseWhere($data['where']);
            }

            if (isset($data['order'])) {
                $query .= ' ORDER BY ' . implode(',', $data['order']);
            }

            if (isset($data['limit'])) {
                $query .= ' LIMIT ' . $data['limit'];
            }
        }
        elseif ($data['type'] == 'DELETE') {
            $query = 'DELETE FROM ' . $data['table'];

            if (!empty($data['where'])) {
                $query .= ' WHERE ' . self::parseWhere($data['where']);
            }

            if (isset($data['order'])) {
                $query .= ' ORDER BY ' . implode(',', $data['order']);
            }

            if (isset($data['limit'])) {
                $query .= ' LIMIT ' . $data['limit'];
            }
        }
        return [$query, $bindings];
    }

    public static function mergeData(array $first, array $second): array
    {
        $result = $first;
        $complete = function($key) use (&$result, $second) {
            $result[$key] = array_merge($result[$key] ?? [], $second[$key] ?? []);
        };

        $complete('values');
        $complete('joins');
        $complete('where');
        $complete('bindings');

        return $result;
    }

    private static function parseWhere(array $where): string
    {
        $operator = $where['operator'] ?? 'AND';
        unset($where['operator']);

        $values = [];
        foreach ($where as $value) {
            $v = (is_array($value)) ? self::parseWhere($value) : $value;
            if (!empty($v)) {
                $values[] = $v;
            }
        }

        if (empty($values)) {
            return '';
        }

        return '(' . implode(" $operator ", $values) . ')';
    }
}