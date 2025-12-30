<?php

declare(strict_types=1);

namespace App\Application\Voice;

final class VoiceWorkEventParser
{
    public function extractDurationMinutes(string $transcript): ?int
    {
        $text = strtolower($transcript);
        $hours = null;
        $minutes = null;

        if (preg_match('/(\d+)\s*(hours|hour|hrs|hr|h)/', $text, $matches)) {
            $hours = (int) $matches[1];
        }

        if (preg_match('/(\d+)\s*(minutes|minute|mins|min|m)/', $text, $matches)) {
            $minutes = (int) $matches[1];
        }

        if ($hours === null && $minutes === null) {
            return null;
        }

        return (int) (($hours ?? 0) * 60 + ($minutes ?? 0));
    }
}
