<?php

namespace Gendiff\GenerateDiff;

use Exception;
use function Functional\sort;

function getFileData(string $path): array
{
    $absolutePath = realpath($path);
    if (!file_exists($absolutePath)) {
        throw new Exception("File does not exist");
    }

    $extension = pathinfo($absolutePath)['extension'];
    $content = file_get_contents($absolutePath);

    return [
        'type' => $extension,
        'data' => $content
    ];
}

function parse(string $data): array
{
    return json_decode($data);

}

function genDiff(string $path1, string $path2): array
{
    $fileData1 = getFileData($path1);
    $fileData2 = getFileData($path2);

    ['type' => $type1, 'data' => $data1] = parse($fileData1);
    ['type' => $type2, 'data' => $data2] = parse($fileData2);

    $diff = getDiff($data1, $data2);
    return $diff;
};

function getDiff(object $data1, object $data2): array
{
    $keys = sort(
        array_unique(
            array_merge(
                get_object_vars($data1),
                get_object_vars($data2)
            )
        ),
        fn ($str1, $str2) => strcmp($str1, $str2)
    );

    return array_map(function(string $key) use ($data1, $data2): array {
        $value1 = $data1->$key ?? null;
        $value2 = $data2->$key ?? null;

        if (!property_exists($data2, $key)) {
            return [
                'key' => $key,
                'type' => 'removed',
                'value' => $value1,
            ];
        }

        if (!property_exists($data1, $key)) {
            return [
                'key' => $key,
                'type' => 'added',
                'value' => $value2,
            ];
        }

        if (is_object($value1) && is_object($value2)) {
            return [
                'key' => $key,
                'type' => 'nested',
                'children' => buildDiff($value1, $value2),
            ];
        }

        if ($value1 !== $value2) {
            return [
                'key' => $key,
                'type' => 'changed',
                'value1' => $value1,
                'value2' => $value2,
            ];
        }

        return [
            'key' => $key,
            'type' => 'unchanged',
            'value' => $value1,
        ];
    }, $keys);
}

function buildDiff(object $data1, object $data2): array
{
    return [
        'type' => 'root',
        'children' => getDiff($data1, $data2),
    ];
}
