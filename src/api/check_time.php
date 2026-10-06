<?php
function isInFuture(?string $timestamp): bool
{
    // Empty or NULL (e.g. bloque_jusqua not set) → not in the future
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