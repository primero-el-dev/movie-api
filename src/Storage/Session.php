<?php

namespace App\Storage;

class Session
{
    public static function start(string $name = null): void
    {
        if ($name) {
            session_name($name);
        }
        session_cache_limiter('nocache');
        session_start();
        session_regenerate_id();
    }

    public static function get(string $name): mixed
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        }

        return null;
    }

    public static function set(string $name, $value): void
    {
        $_SESSION[$name] = $value;
    }

    public static function pop(string $name): mixed
    {
        if (isset($_SESSION[$name])) {
            $value = $_SESSION[$name];
            unset($_SESSION[$name]);

            return $value;
        }

        return null;
    }

    public static function isset(string $name): bool
    {
        return isset($_SESSION[$name]);
    }

    public static function unset(string $name): void
    {
        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }
}