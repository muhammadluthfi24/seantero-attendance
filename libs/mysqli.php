<?php
class nsiDB {

    private array $error = array();

    // Deklarasi properti agar PHP 8.2+ tidak Deprecated
    public string $user;
    public string $password;
    public string $database;
    public string $host;

    public function __construct(string $user, string $password, string $database, string $host = 'localhost') {
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->host = $host;
    }

    public function __destruct() {
        if (!empty($this->error)) {
            $this->_show_error();
        }
    }

    public function show_error(): array {
        return $this->error;
    }

    private function _show_error(): void {
        echo 'Error Database:';
        var_dump($this->error);
    }

    protected function connect(): mysqli|false {
        $db = new mysqli($this->host, $this->user, $this->password, $this->database);
        if ($db->connect_errno) {
            $this->error[] = "Error: Tidak bisa terhubung ke database. " . $db->connect_error;
            return false;
        }
        return $db;
    }

    public function query(string $query): mysqli_stmt|false {
        $db = $this->connect();
        if (!$db) return false;

        $stmt = $db->prepare($query);
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        $stmt->execute();
        $stmt->store_result();
        return $stmt;
    }

    public function insert(string $table, array $data, array $format) {
        if (empty($table) || empty($data)) return false;

        $db = $this->connect();
        if (!$db) return false;

        $format = implode('', $format);
        $format = str_replace('%', '', $format);

        list($fields, $placeholders, $values) = $this->prep_query($data);
        array_unshift($values, $format);

        $stmt = $db->prepare("INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})");
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        call_user_func_array([$stmt, 'bind_param'], $this->ref_values($values));
        $stmt->execute();

        return $stmt->affected_rows ? $db->insert_id : false;
    }

    public function tambah(string $table, array $data) {
        $format = array_fill(0, count($data), '%s');
        return $this->insert($table, $data, $format);
    }

    public function update(string $table, array $data, array $format, array $where, array $where_format): bool {
        if (empty($table) || empty($data)) return false;

        $db = $this->connect();
        if (!$db) return false;

        $format = implode('', $format);
        $format = str_replace('%', '', $format);
        $where_format = implode('', $where_format);
        $where_format = str_replace('%', '', $where_format);
        $format .= $where_format;

        list($fields, $placeholders, $values) = $this->prep_query($data, 'update');

        $where_clause = '';
        $where_values = [];
        $count = 0;
        foreach ($where as $field => $value) {
            if ($count > 0) $where_clause .= ' AND ';
            $where_clause .= $field . '=?';
            $where_values[] = $value;
            $count++;
        }

        array_unshift($values, $format);
        $values = array_merge($values, $where_values);

        $stmt = $db->prepare("UPDATE {$table} SET {$placeholders} WHERE {$where_clause}");
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        call_user_func_array([$stmt, 'bind_param'], $this->ref_values($values));
        $stmt->execute();

        return $stmt->affected_rows !== 0 || empty($this->error);
    }

    public function ubah(string $table, array $data, array $where): bool {
        $format = array_fill(0, count($data), '%s');
        $where_format = array_fill(0, count($where), '%s');
        return $this->update($table, $data, $format, $where, $where_format);
    }

    public function select(string $query): array|false {
        $db = $this->connect();
        if (!$db) return false;

        $stmt = $db->prepare($query);
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function select_one(string $query): object|false|null {
        $db = $this->connect();
        if (!$db) return false;

        $stmt = $db->prepare($query);
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_object();
    }

    public function delete(string $table, int $id): bool {
        $db = $this->connect();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ?");
        if (!$stmt) {
            $this->error[] = 'Error Prepare: ' . $db->error;
            return false;
        }

        $stmt->bind_param('d', $id);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    private function prep_query(array $data, string $type = 'insert'): array {
        $fields = '';
        $placeholders = '';
        $values = [];

        foreach ($data as $field => $value) {
            $fields .= "{$field},";
            $values[] = $value;
            $placeholders .= ($type === 'update') ? $field . '= ?,' : '?,';
        }

        $fields = rtrim($fields, ',');
        $placeholders = rtrim($placeholders, ',');

        return [$fields, $placeholders, $values];
    }

    private function ref_values(array $array): array {
        $refs = [];
        foreach ($array as $key => $value) {
            $refs[$key] = &$array[$key];
        }
        return $refs;
    }
}
