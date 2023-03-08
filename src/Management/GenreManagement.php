<?php

namespace App\Management;

use App\Database\Database;
use Exception;

class GenreManagement
{
    public const SELECT_VALUES = ['id', 'names', 'description'];

    public const NAME_SCHEMA = Common::REQUIRED_VARCHAR_SCHEMA;

    private const ID_SCHEMA = Common::ID_SCHEMA;

    private const DESCRIPTION_SCHEMA = [
        'type' => 'string',
        'maxLength' => 4055,
    ];

    private const GENRE_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'names' => [
                'type' => 'array',
                'items' => self::NAME_SCHEMA,
                'minContains' => 1,
            ],
            'description' => self::DESCRIPTION_SCHEMA,
        ],
    ];

    private const NAMES_STRING_LENGTH = 1023;

    public static function index(array $data): array
    {
        UserManagement::getLoggedUserWithPermissionOrDeny('genre_index');

        checkSchema([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'names' => self::NAME_SCHEMA,
                'description' => self::DESCRIPTION_SCHEMA,
            ],
        ], $data);

        $whereParts = [
            [
                'operator' => 'OR'
            ],
            'is_deleted = FALSE',
        ];
        $bindings = [];
        if (!empty($data['names'])) {
            $binding = ':names';
            $whereParts[0][] = "names LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['names'];
        }
        if (!empty($data['description'])) {
            $binding = ':description';
            $whereParts[0][] = "description LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['description'];
        }

        $queryData = [
            'type' => 'SELECT',
            'table' => 'genre',
            'values' =>  self::SELECT_VALUES,
            'where' => $whereParts,
            'bindings' => $bindings,
        ];

        $genres = Database::selectAll(...Database::buildQuery($queryData));

        return array_map(fn($genre) => array_update($genre, ['names'], fn($n) => json_decode($n)), $genres);
    }

    public static function find(int $id): array
    {
        // UserManagement::getLoggedUserWithPermissionOrDeny('genre_find');

        checkSchema(self::ID_SCHEMA, $id);

        $result = Database::select(sprintf(
            'SELECT %s FROM genre WHERE is_deleted = FALSE AND id = ?',
            implode(',', self::SELECT_VALUES)
        ), [$id]) ?? [];

        return $result ? array_update($result, ['names'], fn($n) => json_decode($n)) : [];
    }

    public static function add(array $data): array
    {
        // UserManagement::getLoggedUserWithPermissionOrDeny('genre_add');

        checkSchema(self::GENRE_SCHEMA + ['required' => ['names', 'description']], $data);

        $wherePart = implode(' OR ', array_map(fn($n) => "JSON_CONTAINS(names, '\"$n\"', '$')", $data['names']));
        $genre = Database::select("SELECT * FROM genre WHERE is_deleted = FALSE AND ($wherePart)");
        if ($genre) {
            throw new Exception('Genre already exists.');
        }

        $data['names'] = json_encode($data['names']);
        if (strlen($data['names']) > self::NAMES_STRING_LENGTH) {
            throw new Exception('Names string is too long.');
        }

        Database::execute('INSERT INTO genre (names, description) VALUES (:names, :description)', $data);

        if (Database::wasCorrect()) {
            return ['success' => "Genre was added successfully"];
        } else {
            throw new Exception("Error happened");
        }
    }

    public static function edit(array $data, int $id): array
    {
        UserManagement::getLoggedUserWithPermissionOrDeny('genre_edit');

        checkSchema(self::GENRE_SCHEMA + ['required' => []], $data);
        checkSchema(self::ID_SCHEMA, $id);

        $genre = Database::select('SELECT * FROM genre WHERE id = ? AND is_deleted = FALSE', [$id]);
        if (!$genre) {
            throw new Exception("Genre doesn't exist.");
        }

        $queryData = [
            'type' => 'UPDATE',
            'table' => 'genre',
            'values' => [],
            'where' => [
                'id = :id',
                'is_deleted = FALSE',
            ],
            'bindings' => [
                ':id' => $id,
            ],
        ];

        if (isset($data['names'])) {
            $wherePart = implode(' OR ', array_map(fn($n) => "JSON_CONTAINS(names, '\"$n\"', '$')", $data['names']));
            $genre = Database::select("SELECT * FROM genre WHERE is_deleted = FALSE AND id = ? AND ($wherePart)", [$id]);
            if ($genre) {
                throw new Exception('Genre with one of given names already exists.');
            }

            $data['names'] = json_encode($data['names']);
            if (strlen($data['names']) > self::NAMES_STRING_LENGTH) {
                throw new Exception('Names string is too long.');
            }
        }

        $queryData['values'] = $data;

        [$query, $bindings] = Database::buildQuery($queryData);
        Database::execute($query, $bindings);

        if (Database::wasCorrect()) {
            return ['success' => "Genre was edited successfully."];
        } else {
            throw new Exception("Error happened.");
        }
    }

    public static function delete(int $id): array
    {
        UserManagement::getLoggedUserWithPermissionOrDeny('genre_delete');

        checkSchema(self::ID_SCHEMA, $id);

        $genre = Database::select('SELECT * FROM genre WHERE is_deleted = FALSE AND id = ?', [$id]);
        if (!$genre) {
            throw new Exception("Genre doesn't exist.");
        }

        Database::execute('UPDATE genre SET is_deleted = TRUE WHERE id = ?', [$id]);

        if (Database::wasCorrect()) {
            return ['success' => "Genre was deleted successfully."];
        } else {
            throw new Exception("Error has happened.");
        }
    }
}