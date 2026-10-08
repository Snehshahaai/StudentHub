<?php
/**
 * StudentHub - MySQL Database Connection (MySQLi)
 *
 * One shared connection plus small helpers that always use prepared
 * statements, so user input is never pasted into SQL:
 *
 *   $student = db_one('SELECT * FROM students WHERE email = ?', [$email]);
 *   $rows    = db_all('SELECT * FROM notices WHERE category = ? LIMIT ?', ['Exam', 10]);
 *   $id      = db_insert('INSERT INTO faqs (category, question, answer) VALUES (?, ?, ?)', [...]);
 *   $changed = db_execute('UPDATE students SET status = ? WHERE student_id = ?', ['active', 2]);
 */

declare(strict_types=1);

/**
 * Return the shared MySQLi connection, opening it on first use.
 * Throws mysqli_sql_exception if the server can't be reached.
 */
function db(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $config = require __DIR__ . '/config.php';

        // Turn MySQL errors into exceptions instead of silent `false` returns
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );
        $conn->set_charset($config['charset']);
        $conn->query("SET time_zone = '{$config['timezone']}'");
    }

    return $conn;
}

/**
 * Prepare and run a statement. Parameter types are picked automatically:
 * int -> i, float -> d, everything else (string, null, bool) -> s.
 */
function db_run(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);

    if ($params) {
        $types = '';
        foreach ($params as $i => $value) {
            if (is_bool($value)) {
                $params[$i] = (int) $value;
                $types .= 'i';
            } else {
                $types .= match (true) {
                    is_int($value)   => 'i',
                    is_float($value) => 'd',
                    default          => 's',
                };
            }
        }
        $stmt->bind_param($types, ...array_values($params));
    }

    $stmt->execute();
    return $stmt;
}

/** All matching rows as associative arrays. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db_run($sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** The first matching row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db_run($sql, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** A single value (e.g. COUNT(*)), or null. */
function db_value(string $sql, array $params = []): mixed
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

/** Run an INSERT and return the new AUTO_INCREMENT id. */
function db_insert(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}

/** Run an UPDATE / DELETE and return the number of affected rows. */
function db_execute(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $count = (int) $stmt->affected_rows;
    $stmt->close();
    return $count;
}

/**
 * Run several queries as one all-or-nothing transaction.
 *   db_transaction(function () { db_insert(...); db_execute(...); });
 */
function db_transaction(callable $work): mixed
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $result = $work($conn);
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
