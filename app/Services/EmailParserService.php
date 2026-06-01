<?php
namespace App\Services;

class EmailParserService
{
    public static function parse(string $text, array $payload): string
    {
        return preg_replace_callback('/{{\s*(.*?)\s*}}/', function ($matches) use ($payload) {
            $key = trim($matches[1]);
            return $payload[$key] ?? '';
        }, $text);
    }
}
