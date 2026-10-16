<?php
function isInFuture(?string $timestamp): bool
{
    if ($timestamp === null || trim($timestamp) === '') {
        return false;
    }

    try {
        $date = new DateTimeImmutable($timestamp);
    } catch (Exception $e) {
        throw new RuntimeException('Invalid timestamp: ' . $timestamp);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return $date > $now;
}