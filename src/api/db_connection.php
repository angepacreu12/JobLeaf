<?php

declare(strict_types=1);
require_once  dirname(__DIR__, 2) . '/src/api/config.php';

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
  		$pdo = get_db_config();
    }

    return $pdo;
}

function update_row(PDO $pdo, string $table, string $id_column, string $id, array $changes, array $binary = []): void {
    $sets = [];
    foreach ($changes as $column => $value) {
        $sets[] = "$column = :$column";
    }

    $stmt = $pdo->prepare("UPDATE $table SET " . implode(', ', $sets) . " WHERE $id_column = :id");

    foreach ($changes as $column => $value) {
        $type = in_array($column, $binary, true) ? PDO::PARAM_LOB : PDO::PARAM_STR;
        $stmt->bindValue(':' . $column, $value, $type);
    }
    
    $stmt->bindValue(':id', $id);
    $stmt->execute();
}