<?php

namespace App\Management;

use App\Database\Database;
use Exception;

class PersonManagement
{
    public const SELECT_VALUES = ['id', 'name', 'surname', 'pseudo', 'country_id', 'description', 'birth', 'death'];

    public const ID_SCHEMA = Common::ID_SCHEMA;

    public const PERSON_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'name' => self::NAME_SCHEMA,
            'surname' => self::SURNAME_SCHEMA,
            'pseudo' => self::PSEUDO_SCHEMA,
            'country' => CountryManagement::NAME_SCHEMA,
            'description' => Common::TEXT_SCHEMA,
            'birth' => Common::DATE_SCHEMA,
            'death' => Common::DATE_SCHEMA,
        ],
    ];

    private const NAME_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
        'pattern' => '^[\w ]+$',
    ];

    private const SURNAME_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
        'pattern' => '^[\w\- ]+$',
    ];

    private const PSEUDO_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
        'pattern' => '^[\w\d\-\_\+\* ]+$',
    ];

    public static function index(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('person_index');

        checkSchema(self::PERSON_SCHEMA, $data);

        $whereParts = [
            [
                'operator' => 'OR',
            ],
            'person.is_deleted = FALSE',
        ];
        $bindings = [];

        if (!empty($data['name'])) {
            $binding = ':name';
            $whereParts[0][] = "person.name LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['name'];
        }
        if (!empty($data['surname'])) {
            $binding = ':surname';
            $whereParts[0][] = "person.surname LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['surname'];
        }
        if (!empty($data['pseudo'])) {
            $binding = ':pseudo';
            $whereParts[0][] = "person.pseudo LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['pseudo'];
        }

        $queryData = Database::mergeData(self::getSelectBegin(), [
            'type' => 'SELECT',
            'table' => 'person',
            'values' => [],
            'where' => $whereParts,
            'bindings' => $bindings,
        ]);

        return self::prepareResultsBeforeDisplay(Database::selectAll(...Database::buildQuery($queryData)));
    }

    public static function find(int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('person_find');

        checkSchema(self::ID_SCHEMA, $id);

        $queryData = Database::mergeData(self::getSelectBegin(), [
            'where' => [
                'person.is_deleted = FALSE',
                'person.id = :person_id',
            ],
            'bindings' => [
                ':person_id' => $id,
            ],
        ]);

        $result = Database::selectAll(...Database::buildQuery($queryData)) ?? [];

        return self::prepareResultsBeforeDisplay($result)[0] ?? [];
    }

    public static function add(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('person_add');

        checkSchema(self::PERSON_SCHEMA + ['required' => ['name', 'surname']], $data);

        $bindings = array_apply_on_keys($data, fn($k) => ":$k");

        if (isset($data['country'])) {
            $queryData = [
                'type' => 'SELECT',
                'table' => 'country',
                'values' => ['id'],
                'where' => [
                    'name = :name',
                    'is_deleted = FALSE',
                ],
                'bindings' => [
                    ':name' => $data['country'],
                ],
            ];
            $result = Database::select(...Database::buildQuery($queryData));

            if (!$result) {
                throw new Exception(sprintf("Country '%s' doesn't exist in our database.", $data['country']));
            }

            $data['country_id'] = $result['id'];
            $bindings[':country_id'] = $result['id'];
        }
        unset($data['country']);
        unset($bindings[':country']);

        Database::execute(sprintf(
            'INSERT INTO person (%s) VALUES (%s)',
            implode(',', array_keys($data)),
            implode(',', array_keys($bindings))
        ), $bindings);

        if (Database::wasCorrect()) {
            $queryData = Database::mergeData(self::getSelectBegin(), [
                'where' => ['person.id = :person_id'],
                'bindings' => [
                    ':person_id' => Database::$db->lastInsertId(),
                ],
            ]);

            // return self::prepareResultsBeforeDisplay(Database::select(...Database::buildQuery($queryData)));
            
            return ['success' => "Person was added successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function edit(array $data, int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('person_edit');

        checkSchema(self::PERSON_SCHEMA + ['required' => []], $data);
        checkSchema(self::ID_SCHEMA, $id);

        $person = Database::select('SELECT * FROM person WHERE id = ? AND is_deleted = FALSE', [$id]);
        if (!$person) {
            throw new Exception("Person doesn't exist.");
        }

        if (isset($data['country'])) {
            $queryData = [
                'type' => 'SELECT',
                'table' => 'country',
                'values' => ['id'],
                'where' => [
                    'name = :name',
                    'is_deleted = FALSE',
                ],
                'bindings' => [
                    ':name' => $data['country'],
                ],
            ];
            $result = Database::select(...Database::buildQuery($queryData));

            if (!$result) {
                throw new Exception(sprintf("Country '%s' doesn't exist in our database.", $data['country']));
            }

            $data['country_id'] = $result['id'];
            $bindings[':country_id'] = $result['id'];
        }
        unset($data['country']);
        unset($bindings[':country']);

        $queryData = [
            'type' => 'UPDATE',
            'table' => 'person',
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
            return ['success' => "Person was edited successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function delete(int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('person_delete');

        checkSchema(self::ID_SCHEMA, $id);

        $person = Database::select('SELECT * FROM person WHERE is_deleted = FALSE AND id = ?', [$id]);
        if (!$person) {
            throw new Exception("Person doesn't exist.");
        }

        Database::execute('UPDATE person SET is_deleted = TRUE WHERE id = ?', [$id]);

        if (Database::wasCorrect()) {
            return ['success' => "Person was deleted successfully."];
        }

        throw new Exception("Error has happened.");
    }

    private static function getSelectBegin(): array
    {
        $values = array_merge(
            array_map(fn($m) => "person.$m", self::SELECT_VALUES),
            [
                'person_movie_role.role AS movie_role',
                'country.name AS country',
            ],
            array_map(fn($m) => "movie.$m AS movie_$m", MovieManagement::SELECT_VALUES)
        );
        
        return [
            'type' => 'SELECT',
            'values' => $values,
            'table' => 'person',
            'joins' => [
                'LEFT JOIN person_movie_role ON person_movie_role.person_id = person.id',
                'LEFT JOIN movie ON movie.id = person_movie_role.movie_id',
                'LEFT JOIN country ON country.id = person.country_id',
            ],
        ];
    }

    private static function prepareResultsBeforeDisplay(array $result): array
    {
        // dd($result);
        $result = group_by_with_tuples($result, 'id', [
            'roles' => array_merge(array_map(fn($m) => "movie_$m", MovieManagement::SELECT_VALUES), ['movie_role']),
        ]);

        return array_map(function($person) {
            $person['roles'] = array_filter($person['roles'], fn($r) => $r['movie_id']);
            return $person;
        }, $result);
    }
}