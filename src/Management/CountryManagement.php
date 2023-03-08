<?php

namespace App\Management;

use App\Database\Database;
use Exception;

class CountryManagement
{
    public const ID_SCHEMA = Common::ID_SCHEMA;

    public const SELECT_VALUES = ['id', 'name', 'code'];

    public const NAME_SCHEMA = Common::REQUIRED_VARCHAR_SCHEMA;

    public const CODE_SCHEMA = Common::REQUIRED_VARCHAR_SCHEMA;

    public const COUNTRY_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'name' => self::NAME_SCHEMA,
            'code' => self::CODE_SCHEMA,
        ],
    ];

    public const COUNTRY_ARRAY_SCHEMA = [
        'type' => 'array',
        'minContains' => 1,
        'items' => self::NAME_SCHEMA,
    ]; 

    public static function index(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('country_index');

        checkSchema(self::COUNTRY_SCHEMA, $data);

        $whereParts = [
            [
                'operator' => 'OR',
            ],
            'is_deleted = FALSE',
        ];
        $bindings = [];
        if (!empty($data['name'])) {
            $binding = ':name';
            $whereParts[0][] = "name LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['name'];
        }
        if (!empty($data['code'])) {
            $binding = ':code';
            $whereParts[0][] = "code LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['code'];
        }

        $queryData = [
            'type' => 'SELECT',
            'table' => 'country',
            'values' => self::SELECT_VALUES,
            'where' => $whereParts,
            'bindings' => $bindings,
        ];

        return Database::selectAll(...Database::buildQuery($queryData));
    }

    public static function find(int $id): array
    {
        // UserManagement::getLoggedUserWithPermissionOrDeny('country_find');

        checkSchema(self::ID_SCHEMA, $id);

        $result = Database::select(sprintf(
            'SELECT %s FROM country WHERE is_deleted = FALSE AND id = ?',
            implode(',', self::SELECT_VALUES)
        ), [$id]) ?? [];

        return $result ?: [];
    }

    public static function add(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('country_add');

        checkSchema(self::COUNTRY_SCHEMA + ['required' => ['name', 'code']], $data);

        $bindings = array_apply_on_keys($data, fn($k) => ":$k");
        $country = Database::select(
            "SELECT * FROM country WHERE is_deleted = FALSE AND (name = :name OR code = :code)",
            array_pick($bindings, [':name', ':code'])
        );
        if ($country) {
            throw new Exception('Country already exists.');
        }

        Database::execute(sprintf(
            'INSERT INTO country (%s) VALUES (%s)',
            implode(',', array_keys($data)),
            implode(',', array_keys($bindings))
        ), $bindings);

        if (Database::wasCorrect()) {
            return ['success' => "Country  was added successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function edit(array $data, int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('country_edit');

        checkSchema(self::COUNTRY_SCHEMA + ['required' => []], $data);
        checkSchema(self::ID_SCHEMA, $id);

        $country = Database::select('SELECT * FROM country WHERE id = ? AND is_deleted = FALSE', [$id]);
        if (!$country) {
            throw new Exception("Country doesn't exist.");
        }

        $queryData = [
            'type' => 'UPDATE',
            'table' => 'country',
            'values' => $data,
            'where' => [
                'id = :id',
                'is_deleted = FALSE',
            ],
            'bindings' => [
                ':id' => $id,
            ],
        ];

        Database::execute(...Database::buildQuery($queryData));

        if (Database::wasCorrect()) {
            return ['success' => "Country was edited successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function delete(int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('country_delete');

        checkSchema(self::ID_SCHEMA, $id);

        $genre = Database::select('SELECT * FROM country WHERE is_deleted = FALSE AND id = ?', [$id]);
        if (!$genre) {
            throw new Exception("Country doesn't exist.");
        }

        Database::execute('UPDATE country SET is_deleted = TRUE WHERE id = ?', [$id]);

        if (Database::wasCorrect()) {
            return ['success' => "Country was deleted successfully."];
        }

        throw new Exception("Error has happened.");
    }
}