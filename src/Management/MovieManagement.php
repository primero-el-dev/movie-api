<?php

namespace App\Management;

use App\Management\Common;
use App\Database\Database;

class MovieManagement
{
    public const SELECT_VALUES = ['id', 'title', 'description', 'length', 'created_at'];

    public const ID_SCHEMA = Common::ID_SCHEMA;

    public const TITLE_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
        'pattern' => '^[\w\-\d\+\=\(\)\,\.\/\?\:\;\!\@\#\$\%\^\&\* ]+$',
    ];

    public const DESCRIPTION_SCHEMA = [
        'type' => 'string',
        'maxLength' => 4095,
    ];

    public const LENGTH_SCHEMA = [
        'type' => 'string',
        'pattern' => '([0-1]\d)|(2[0-3])\:([0-5]\d)\:([0-5]\d)',
    ];

    public const MOVIE_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'title' => self::TITLE_SCHEMA,
            'description' => self::DESCRIPTION_SCHEMA,
            'length' => self::LENGTH_SCHEMA,
            'created_at' => Common::YEAR_SCHEMA,
        ],
    ];

    public const ROLES_SCHEMA = [
        'type' => 'array',
        'minContains' => 1,
        'items' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'role' => self::ROLE_SCHEMA,
                'person_id' => PersonManagement::ID_SCHEMA,
            ],
        ],
    ];

    public const ROLE_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
        'pattern' => '^[\w\d]([\w\d ]+[\w\d])*$',
    ];

    public const GENRES_SCHEMA = [
        'type' => 'array',
        'minContains' => 1,
        'items' => GenreManagement::NAME_SCHEMA,
    ];

    public static function index(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('movie_index');

        checkSchema(self::MOVIE_SCHEMA, $data);

        $whereParts = [
            [
                'operator' => 'OR',
            ],
            'movie.is_deleted = FALSE',
        ];
        $bindings = [];

        if (!empty($data['title'])) {
            $binding = ':title';
            $whereParts[0][] = "movie.title LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['title'];
        }
        if (!empty($data['description'])) {
            $binding = ':description';
            $whereParts[0][] = "movie.description LIKE CONCAT('%', $binding, '%')";
            $bindings[$binding] = $data['description'];
        }
        if (!empty($data['created_at'])) {
            $binding = ':created_at';
            $whereParts[0][] = "movie.created_at = $binding";
            $bindings[$binding] = $data['created_at'];
        }

        $queryData = Database::mergeData(self::getSelectBegin(), [
            'type' => 'SELECT',
            'table' => 'movie',
            'values' => [],
            'where' => $whereParts,
            'bindings' => $bindings,
        ]);

        return self::prepareResultsBeforeDisplay(Database::selectAll(...Database::buildQuery($queryData)));
    }

    public static function find(int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('movie_find');

        checkSchema(self::ID_SCHEMA, $id);

        $queryData = Database::mergeData(self::getSelectBegin(), [
            'where' => [
                'movie.is_deleted = FALSE',
                'movie.id = :movie_id',
            ],
            'bindings' => [
                ':movie_id' => $id,
            ],
        ]);

        $result = Database::selectAll(...Database::buildQuery($queryData)) ?? [];

        return self::prepareResultsBeforeDisplay($result)[0] ?? [];
    }

    public static function add(array $data): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('movie_add');

        checkSchema(self::MOVIE_SCHEMA + ['required' => ['title']], $data);

        $bindings = array_apply_on_keys($data, fn($k) => ":$k");

        Database::execute(sprintf(
            'INSERT INTO movie (%s) VALUES (%s)',
            implode(',', array_keys($data)),
            implode(',', array_keys($bindings))
        ), $bindings);

        if (Database::wasCorrect()) {
            return ['success' => "Movie was added successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function edit(array $data, int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('movie_edit');

        checkSchema(self::MOVIE_SCHEMA + ['required' => []], $data);
        checkSchema(self::ID_SCHEMA, $id);

        self::getMovieOrThrowException($id);

        $queryData = [
            'type' => 'UPDATE',
            'table' => 'movie',
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
            return ['success' => "Movie was edited successfully."];
        }

        throw new Exception("Error happened.");
    }

    public static function delete(int $id): array
    {
//        UserManagement::getLoggedUserWithPermissionOrDeny('movie_delete');

        checkSchema(self::ID_SCHEMA, $id);

        self::getMovieOrThrowException($id);

        Database::execute('UPDATE movie SET is_deleted = TRUE WHERE id = ?', [$id]);

        if (Database::wasCorrect()) {
            return ['success' => "Movie was deleted successfully."];
        }

        throw new Exception("Error has happened.");
    }

    public static function addCountries(array $data, int $id): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $id);
        checkSchema(CountryManagement::COUNTRY_ARRAY_SCHEMA, $data);

        $data = array_unique(array_map(fn($d) => trim(strtolower($d)), $data));

        // Check if movie exists
        self::getMovieOrThrowException($id);

        // Check if all countries exist
        $bindings = implode(',', array_map(fn($d) => '?', array_filter($data, fn($d) => $d)));
        $countries = Database::selectAll("
            SELECT country.id AS country_id, movie_country.movie_id 
            FROM country 
            LEFT JOIN movie_country ON movie_country.country_id = country.id
            WHERE country.is_deleted = FALSE 
              AND country.name IN ($bindings)
              AND (movie_country.movie_id = ? OR movie_country.movie_id IS NULL)", 
            array_merge($data, [$id])
        );
        if (count($countries) !== count($data)) {
            throw new Exception("Some of countries don't exist in our database.");
        }

        // Make only connections that are missing
        if ($data) {
            $countriesToAdd = array_values(array_map(
                fn($c) => $c['country_id'], 
                array_filter($countries, fn($c) => is_null($c['movie_id']))
            ));

            $bindings = implode(',', array_map(fn($d) => "($id, ?)", $countriesToAdd));

            Database::execute("INSERT INTO movie_country (movie_id, country_id) VALUES $bindings", $countriesToAdd);
        }

        if (Database::wasCorrect()) {
            return ['success' => "Countries are assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    public static function deleteCountries(array $data, int $id): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $id);
        checkSchema(CountryManagement::COUNTRY_ARRAY_SCHEMA, $data);

        $data = array_unique(array_map(fn($d) => trim(strtolower($d)), $data));

        // Check if movie exists
        self::getMovieOrThrowException($id);

        // Check if all countries exist
        if ($data) {
            $bindings = implode(',', array_map(fn($d) => '?', array_filter($data, fn($d) => $d)));
            $countries = Database::execute("
                DELETE movie_country
                FROM movie_country 
                INNER JOIN country ON movie_country.country_id = country.id
                WHERE country.name IN ($bindings)
                  AND movie_country.movie_id = ?", 
                array_merge($data, [$id])
            );
        }

        if (Database::wasCorrect()) {
            return ['success' => "Countries are not assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    public static function addRoles(array $data, int $movieId): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $movieId);
        checkSchema(self::ROLES_SCHEMA, $data);

        // Check if movie exists
        self::getMovieOrThrowException($movieId);

        // Filter unnecessary data
        $data = array_values(array_unique($data, SORT_REGULAR));
        foreach ($data as $role) {
            if (empty($role['role']) || empty($role['person_id'])) {
                unset($role);
            }
        }

        // Check if all people exist
        $personIds = array_unique(array_map(fn($d) => $d['person_id'], $data));
        $bindingsPlaceholder = implode(',', array_map(fn($r) => '?', $personIds));
        $people = Database::selectAll("SELECT id FROM person WHERE is_deleted = FALSE AND id IN ($bindingsPlaceholder)", $personIds);
        if (count($people) !== count($personIds)) {
            throw new Exception("Some of people don't exist in our database.");
        }

        // Filter existing roles
        $bindings = [$movieId];
        $bindingsPlaceholder = [];
        foreach ($data as $role) {
            $bindingsPlaceholder[] = '(person_id = ? AND role = ?)';
            $bindings = array_merge($bindings, [$role['person_id'], $role['role']]);
        }
        $bindingsPlaceholder = implode(' OR ', $bindingsPlaceholder);

        $roles = Database::selectAll("
            SELECT *
            FROM person_movie_role
            WHERE movie_id = ? AND ($bindingsPlaceholder)", 
            $bindings
        );

        $count = count($data);
        foreach ($roles as $role) {
            for ($i = 0; $i < $count; $i++) {
                if ($role['person_id'] === $data[$i]['person_id'] && $role['role'] === $data[$i]['role']) {
                    unset($data[$i]);
                }
            }
        }

        // Add missing roles
        if ($data) {
            $bindings = [];
            foreach ($data as $role) {
                $bindings = array_merge($bindings, [$role['person_id'], $movieId, strtolower($role['role'])]);
            }
            $bindingsPlaceholder = implode(',', array_map(fn($d) => '(?,?,?)', $data));

            Database::execute("INSERT INTO person_movie_role (person_id, movie_id, role) VALUES $bindingsPlaceholder", $bindings);
        }

        if (Database::wasCorrect()) {
            return ['success' => "Roles are assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    public static function deleteRoles(array $data, int $movieId): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $movieId);
        checkSchema(self::ROLES_SCHEMA, $data);

        // Check if movie exists
        self::getMovieOrThrowException($movieId);

        // Remove roles
        if ($data) {
            $bindings = [];
            foreach ($data as $role) {
                $bindings = array_merge($bindings, [$role['person_id'], $movieId, strtolower($role['role'])]);
            }
            $bindingsPlaceholder = implode(' OR ', array_map(fn($d) => '(person_id = ? AND movie_id = ? AND role = ?)', $data));

            Database::execute("DELETE FROM person_movie_role WHERE $bindingsPlaceholder", $bindings);
        }

        if (Database::wasCorrect()) {
            return ['success' => "Roles are not assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    public static function addGenres(array $data, int $movieId): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $movieId);
        checkSchema(self::GENRES_SCHEMA, $data);

        // Check if movie exists
        self::getMovieOrThrowException($movieId);

        // Filter unnecessary data
        $data = array_values(array_unique($data, SORT_REGULAR));

        // Check if all genres exist
        $genreIds = array_unique(array_map(fn($d) => '%"' . $d . '"%', $data));
        $bindingsPlaceholder = implode(',', array_map(fn($r) => 'names LIKE ?', $genreIds));
        $genres = Database::selectAll("SELECT id FROM genre WHERE is_deleted = FALSE AND ($bindingsPlaceholder)", $genreIds);
        if (count($genres) !== count($genreIds)) {
            throw new Exception("Some of genres don't exist in our database.");
        }
        $genres = array_values(array_unique(array_column($genres, 'id')));

        // Filter existing genres
        $bindingsPlaceholder = implode(' OR ', array_map(fn($g) => 'genre_id = ?', $genres));
        $movieGenres = Database::selectAll(
            "SELECT * FROM movie_genre WHERE movie_id = ? AND ($bindingsPlaceholder)", 
            array_merge([$movieId], $genres)
        );

        $count = count($genres);
        foreach ($movieGenres as $movieGenre) {
            for ($i = 0; $i < $count; $i++) {
                if ($movieGenre['genre_id'] === $genres[$i]) {
                    unset($genres[$i]);
                }
            }
        }

        // Add missing genres
        if ($genres) {
            $bindings = [];
            foreach ($genres as $genre) {
                $bindings = array_merge($bindings, [$movieId, strtolower($genre)]);
            }
            $bindingsPlaceholder = implode(',', array_map(fn($d) => '(?,?)', $data));

            Database::execute("INSERT INTO movie_genre (movie_id, genre_id) VALUES $bindingsPlaceholder", $bindings);
        }

        if (Database::wasCorrect()) {
            return ['success' => "Genres are assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    public static function deleteGenres(array $data, int $movieId): array
    {
        // Validate data
        checkSchema(self::ID_SCHEMA, $movieId);
        checkSchema(self::GENRES_SCHEMA, $data);

        // Check if movie exists
        self::getMovieOrThrowException($movieId);

        // Remove roles
        $bindings = array_merge([$movieId], array_map(fn($g) => '%"' . strtolower($g) . '"%', $data));
        $bindingsPlaceholder = implode(' OR ', array_map(fn($d) => 'genre.names LIKE ?', $data));

        Database::execute("DELETE movie_genre FROM movie_genre LEFT JOIN genre ON genre.id = movie_genre.genre_id WHERE movie_id = ? AND ($bindingsPlaceholder)", $bindings);

        if (Database::wasCorrect()) {
            return ['success' => "Genres are not assigned to movie."];
        }

        throw new Exception("Error has happened.");
    }

    private static function getMovieOrThrowException(int $id): array
    {
        $movie = Database::select('SELECT * FROM movie WHERE is_deleted = FALSE AND id = ?', [$id]);
        if (!$movie) {
            throw new Exception("Movie doesn't exist.");
        }

        return $movie;
    }

    private static function getSelectBegin(): array
    {
        $values = array_merge(
            array_map(fn($m) => "movie.$m", self::SELECT_VALUES),
            array_map(fn($m) => "person.$m AS person_$m", PersonManagement::SELECT_VALUES),
            array_map(fn($m) => "genre.$m AS genre_$m", GenreManagement::SELECT_VALUES),
            array_map(fn($m) => "country.$m AS country_$m", CountryManagement::SELECT_VALUES),
            [
                'person_movie_role.role AS person_role',
            ],
        );
        
        return [
            'type' => 'SELECT',
            'values' => $values,
            'table' => 'movie',
            'joins' => [
                'LEFT JOIN person_movie_role ON person_movie_role.movie_id = movie.id',
                'LEFT JOIN person ON person.id = person_movie_role.person_id',
                'LEFT JOIN movie_country ON movie_country.movie_id = movie.id',
                'LEFT JOIN country ON country.id = movie_country.country_id',
                'LEFT JOIN movie_genre ON movie_genre.movie_id = movie.id',
                'LEFT JOIN genre ON genre.id = movie_genre.genre_id',
            ],
        ];
    }

    private static function prepareResultsBeforeDisplay(array $result): array
    {
        foreach ($result as &$r) {
            $r['genre_names'] = json_decode($r['genre_names'] ?? '[]');
        }

        $result = group_by_with_tuples($result, 'id', [
            'roles' => array_merge(array_map(fn($m) => "person_$m", PersonManagement::SELECT_VALUES), ['person_role']),
            'countries' => array_map(fn($m) => "country_$m", CountryManagement::SELECT_VALUES),
            'genres' => array_map(fn($m) => "genre_$m", GenreManagement::SELECT_VALUES),
        ]);

        return array_map(function($movie) {
            $movie['roles'] = array_unique(array_filter($movie['roles'], fn($r) => $r['person_id']), SORT_REGULAR);
            $movie['genres'] = array_unique(array_filter($movie['genres'], fn($r) => $r['genre_id']), SORT_REGULAR);
            $movie['countries'] = array_unique(array_filter($movie['countries'], fn($r) => $r['country_id']), SORT_REGULAR);
            return $movie;
        }, $result);
    }
}