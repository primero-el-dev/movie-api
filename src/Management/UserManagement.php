<?php

namespace App\Management;

use App\Database\Database;
use App\Exception\AccessDeniedException;
use App\Storage\Session;
use DateTime;

class UserManagement
{
    private const SESSION_USER_ID_KEY = 'user_id';

    private const ID_SCHEMA = Common::ID_SCHEMA;

    private const EMAIL_SCHEMA = [
        'type' => 'string',
        'minLength' => 7,
        'maxLength' => 255,
    ];

    private const PASSWORD_SCHEMA = [
        'type' => 'string',
        'minLength' => 8,
        'maxLength' => 60,
    ];

    private const USER_TYPE_SCHEMA = [
        'type' => 'string',
        'enum' => ['user', 'admin', 'global_admin'],
    ];

    public static function login(array $data): array
    {
        checkSchema([
            'type' => 'object',
            'required' => ['email', 'password'],
            'additionalProperties' => false,
            'properties' => [
                'email' => self::EMAIL_SCHEMA,
                'password' => self::PASSWORD_SCHEMA,
            ],
        ], $data);

        $user = Database::select('SELECT * FROM user WHERE email = ?', [$data['email']]);
        if (!$user || !password_verify($data['password'], $user['password'])) {
            throw new Exception('Wrong credentials');
        }

        Session::set(self::SESSION_USER_ID_KEY, $user['id']);

        return ['success' => "You've logged in successfully"];
    }

    public static function logout(): array
    {
        Session::unset(self::SESSION_USER_ID_KEY);

        return ['success' => "You've logged out successfully"];
    }

    public static function selfEdit(array $data): array
    {
        $loggedUser = self::getLoggedUser();

        if (!$loggedUser) {
            throw new AccessDeniedException();
        }

        checkSchema([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'email' => self::EMAIL_SCHEMA,
                'password' => self::PASSWORD_SCHEMA,
            ],
        ], $data);

        $updateData = [
            'type' => 'UPDATE',
            'table' => 'user',
            'values' => $data,
            'where' => ['id = :id', 'is_deleted = 0'],
            'bindings' => [':id' => $loggedUser['id']],
        ];

        Database::execute(...Database::buildQuery($updateData));

        if (Database::wasCorrect()) {
            return ['success' => 'Your profile has been updated'];
        } else {
            throw new Exception('Something has gone wrong');
        }
    }

    public static function add(array $data): array
    {
        UserManagement::getLoggedUserWithPermissionOrDeny('user_add');

        checkSchema([
            'type' => 'object',
            'required' => ['email', 'password', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'email' => self::EMAIL_SCHEMA,
                'password' => self::PASSWORD_SCHEMA,
                'type' => self::USER_TYPE_SCHEMA,
            ],
        ], $data);

        $user = Database::select('SELECT * FROM user WHERE email = ?', [$data['email']]);
        if ($user) {
            throw new Exception('Wrong data');
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        Database::execute('INSERT INTO user (email, password, type) VALUES (:email, :password, :type)', $data);

        if (Database::wasCorrect()) {
            return ['success' => "User was added successfully"];
        } else {
            throw new Exception("Error happened");
        }
    }

    public static function delete(int $id): array
    {
        $loggedUser = self::getLoggedUser();

        if (!$loggedUser) {
            throw new AccessDeniedException();
        }
        if (!self::userHasPermission($loggedUser, 'user_delete') && $loggedUser['id'] != $id) {
            throw new AccessDeniedException();
        }

        checkSchema(self::ID_SCHEMA, $id);

        $user = Database::select('SELECT * FROM user WHERE id = ? AND is_deleted = 0', [$id]);
        if (!$user) {
            throw new Exception('User missing.');
        }

        Database::execute('UPDATE user SET is_deleted = TRUE WHERE id = ?', [$id]);

        if (Database::wasCorrect()) {
            return ['success' => "User was deleted successfully."];
        } else {
            throw new Exception("User wasn't deleted.");
        }
    }

    public static function getLoggedUserWithPermissionOrDeny(string $permission): array
    {
        $loggedUser = UserManagement::getLoggedUser();

        if (!$loggedUser) {
            throw new AccessDeniedException();
        }
        if (!UserManagement::userHasPermission($loggedUser, $permission)) {
            throw new AccessDeniedException();
        }

        return $loggedUser;
    }

    public static function getLoggedUser(): array|null
    {
        $userData = Database::selectAll('
            SELECT u.*, up.blocked_to, p.name
            FROM user u
            INNER JOIN user_permission up ON up.user_id = u.id
            INNER JOIN permission p ON p.id = up.permission_id
            WHERE u.id = ? AND u.is_deleted = 0',
            [Session::get(self::SESSION_USER_ID_KEY)]
        );

        if ($userData) {
            return group_by_with_tuples($userData, 'id', ['permissions' => ['blocked_to', 'name']])[0];
        }

        return null;
    }

    public static function userHasPermission(array $user, string $permission): bool
    {
        if (dev()) {
            checkSchema([
                'type' => 'object',
                'required' => ['id'],
                'properties' => [
                    'permissions' => [
                        'type' => 'array',
                        "prefixItems" => [
                            [
                                'blocked_to' => 'string',
                                'pattern' => '\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}',
                            ],
                            [
                                'name' => 'string',
                            ],
                        ],
                    ],
                ],
            ], $user);
        }

        $perm = array_filter($user['permissions'], fn($p) => $p['name'] === $permission);

        return $perm && $perm[0]['blocked_to'] < (new DateTime())->format('Y-m-d H:i:s');
    }
}