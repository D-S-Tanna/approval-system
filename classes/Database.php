<?php
/**
 * Database Abstraction Layer
 * File: classes/Database.php
 */

class Database {
    private $pdo;
    private $stmt;
    private $error;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    /**
     * Prepare a SQL statement
     * @param string $query
     * @return $this
     */
    public function query($query) {
        try {
            $this->stmt = $this->pdo->prepare($query);
            return $this;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database query error: " . $this->error);
            return false;
        }
    }
    
    /**
     * Bind parameters to prepared statement
     * @param string $param
     * @param mixed $value
     * @param int $type
     * @return $this
     */
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }
    
    /**
     * Execute prepared statement
     * @return bool
     */
    public function execute() {
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database execute error: " . $this->error);
            return false;
        }
    }
    
    /**
     * Get all results as array
     * @return array
     */
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single result
     * @return array|false
     */
    public function single() {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get row count
     * @return int
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    /**
     * Get last insert ID
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Check if in transaction
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Get error message
     * @return string
     */
    public function getError() {
        return $this->error;
    }
    
    /**
     * Insert data into table
     * @param string $table
     * @param array $data
     * @return bool|int
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldList = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldList}) VALUES ({$placeholders})";
        
        if ($this->query($sql)) {
            foreach ($data as $key => $value) {
                $this->bind(':' . $key, $value);
            }
            
            if ($this->execute()) {
                return $this->lastInsertId();
            }
        }
        
        return false;
    }
    
    /**
     * Update data in table
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $whereParams
     * @return bool
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        if ($this->query($sql)) {
            // Bind data parameters
            foreach ($data as $key => $value) {
                $this->bind(':' . $key, $value);
            }
            
            // Bind where parameters
            foreach ($whereParams as $key => $value) {
                $this->bind($key, $value);
            }
            
            return $this->execute();
        }
        
        return false;
    }
    
    /**
     * Delete data from table
     * @param string $table
     * @param string $where
     * @param array $whereParams
     * @return bool
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        if ($this->query($sql)) {
            foreach ($whereParams as $key => $value) {
                $this->bind($key, $value);
            }
            
            return $this->execute();
        }
        
        return false;
    }
    
    /**
     * Select data from table
     * @param string $table
     * @param string $where
     * @param array $whereParams
     * @param string $orderBy
     * @param int $limit
     * @param int $offset
     * @return array|false
     */
    public function select($table, $where = '1=1', $whereParams = [], $orderBy = '', $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$table} WHERE {$where}";
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }
        }
        
        if ($this->query($sql)) {
            foreach ($whereParams as $key => $value) {
                $this->bind($key, $value);
            }
            
            return $this->resultSet();
        }
        
        return false;
    }
    
    /**
     * Count records in table
     * @param string $table
     * @param string $where
     * @param array $whereParams
     * @return int
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        
        if ($this->query($sql)) {
            foreach ($whereParams as $key => $value) {
                $this->bind($key, $value);
            }
            
            $result = $this->single();
            return $result ? (int)$result['count'] : 0;
        }
        
        return 0;
    }
    
    /**
     * Check if record exists
     * @param string $table
     * @param string $where
     * @param array $whereParams
     * @return bool
     */
    public function exists($table, $where, $whereParams = []) {
        return $this->count($table, $where, $whereParams) > 0;
    }
}
?>