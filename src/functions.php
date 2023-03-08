<?php

function array_rename_key(array $array, string|int $old, string|int $new): array
{
    if (array_key_exists($old, $array)) {
        $array[$new] = $array[$old];
        unset($array[$old]);
    }

    return $array;
}

function array_rename_keys(array $array, array $oldNewMap): array
{
    foreach ($oldNewMap as $old => $new) {
        $array = array_rename_key($array, $old, $new);
    }

    return $array;
}

function array_rename_key_func(array $array, string|int $key, callable $func): array
{
    if (array_key_exists($key, $array)) {
        $array[$func($key)] = $array[$key];
        unset($array[$key]);
    }

    return $array;
}

function array_apply_on_keys(array $array, callable $func): array
{
    foreach ($array as $old => $value) {
        $array = array_rename_key($array, $old, $func($old));
    }

    return $array;
}

function group_by(array $array, int|string $groupedByKey, array $operations): array
{
    $keys = array_keys($array[0]);
    $result = [];
    foreach ($array as $single) {
        if (!isset($result[$single[$groupedByKey]])) {
            $result[$single[$groupedByKey]] = [];
        }

        if (!$result[$single[$groupedByKey]]) {
            $result[$single[$groupedByKey]] = $single;
        } else {
            foreach ($keys as $key) {
                if (isset($operations[$key])) {
                    $result[$single[$groupedByKey]][$key] = $operations[$key]($result[$single[$groupedByKey]][$key], $single[$key]);
                } else {
                    $result[$single[$groupedByKey]][$key] = $single[$key];
                }
            }
        }
    }

    return array_values($result);
}

// TODO: add option to modify key name after operation
function group_by_with_tuples(array $array, int|string $groupedByKey, array $tuples): array
{
    $result = [];
    foreach ($array as $single) {
        if (!isset($result[$single[$groupedByKey]])) {
            foreach ($tuples as $final => $keys) {
                $single[$final] = [array_pick($single, $keys)];
                $single = array_remove_keys($single, $keys);
            }

            $result[$single[$groupedByKey]] = $single;
        } else {
            foreach ($tuples as $final => $keys) {
                $temp = array_pick($single, $keys);
                $result[$single[$groupedByKey]][$final][] = $temp;
            }
        }
    }

    return array_values($result);
}

function array_remove_keys(array $array, array $keys): array
{
    foreach ($keys as $key) {
        unset($array[$key]);
    }

    return $array;
}

function array_pick(array $array, array $keys): array
{
    $new = [];
    foreach ($array as $key => $value) {
        foreach ($keys as $k) {
            if ($key === $k) {
                $new[$key] = $value;
            }
        }
    }

    return $new;
}

function array_key_by(array $array, int|string $key): array
{
    $values = array_values($array);
    $keys = array_column($array, $key);

    return array_combine($keys, $values);
}

function array_join(array $first, array $firstKeys, array $second, array $secondKeys): array
{
    return array_merge(
        array_pick($first, $firstKeys),
        array_pick($second, $secondKeys)
    );
}

function array_update(mixed $array, array $keys, callable $func): array
{
    $result = &$array;
    foreach ($keys as $key) {
        if (array_key_exists($key, $result)) {
            $result = &$result[$key];
        } else {
            throw new Exception("Path [" . implode(',', $keys) . "] doesn't exist in array " . json_encode($array));
        }
    }
    $result = $func($result);

    return $array;
}

function array_to_object($array)
{
    if (is_array($array) && array_is_list($array)) {
        $obj = [];
    } else {
        $obj = new stdClass();
    }

    foreach ($array as $key => $value) {
        $processed = (is_array($value)) ? array_to_object($value) : $value;

        if (is_string($key)) {
            $obj->{$key} = $processed;
        } else {
            $obj[] = $processed;
        }
    }

    return $obj;
}