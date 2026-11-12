<?php

namespace App\Services;

class AudioEventNormalizer
{
    /**
     * Map upstream phrases or ball event text to standardized normalized codes.
     */
    public static function normalize(?string $eventText): string
    {
        if (empty($eventText)) {
            return 'BALL_DOT';
        }

        $text = strtolower(trim($eventText));

        if (str_contains($text, 'wicket') || str_contains($text, 'out') || str_contains($text, 'caught') || str_contains($text, 'bowled') || str_contains($text, 'lbw') || str_contains($text, 'run out') || str_contains($text, 'stumped')) {
            return 'BALL_WICKET';
        }

        if (str_contains($text, 'six') || str_contains($text, 'maximum') || $text === '6') {
            return 'BALL_6';
        }

        if (str_contains($text, 'four') || str_contains($text, 'boundary') || $text === '4') {
            return 'BALL_4';
        }

        if (str_contains($text, 'no ball') || str_contains($text, 'noball') || str_contains($text, 'nb')) {
            return 'BALL_NOBALL';
        }

        if (str_contains($text, 'wide') || str_contains($text, 'wd')) {
            return 'BALL_WIDE';
        }

        if (str_contains($text, '1 run') || str_contains($text, 'single') || $text === '1') {
            return 'BALL_1';
        }

        if (str_contains($text, '2 runs') || $text === '2') {
            return 'BALL_2';
        }

        if (str_contains($text, '3 runs') || $text === '3') {
            return 'BALL_3';
        }

        return 'BALL_DOT';
    }
}

