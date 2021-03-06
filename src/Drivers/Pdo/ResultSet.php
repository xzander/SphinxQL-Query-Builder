<?php

namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\ResultSetException;
use Foolz\SphinxQL\Drivers\ResultSetInterface;
use PDO;
use PDOStatement;

class ResultSet implements ResultSetInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var \mysqli_result
     */
    protected $result;

    /**
     * @var null|array
     */
    protected $fields = array();

    /**
     * @var int
     */
    protected $num_rows = 0;

    /**
     * @var null|array
     */
    protected $stored = null;

    /**
     * @var int
     */
    protected $affected_rows = 0; // leave to 0 so SELECT etc. will be coherent

    /**
     * @var null|int
     */
    protected $current_row = null;

    /**
     * @var null|array
     */
    protected $fetched = null;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;

        if ($this->statement->columnCount() > 0) {
            $this->num_rows = $this->statement->rowCount();

            for ($i = 0; $i < $this->statement->columnCount(); $i++) {
                $this->fields[] = $this->statement->getColumnMeta($i);
            }

            $this->store();

        } else {
            $this->affected_rows = $this->statement->rowCount();
            $this->store();
        }
    }

    /**
     * Store all the data in this object and free the mysqli object
     *
     * @return static $this
     */
    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        if ($this->statement->columnCount() > 0) {
            $this->stored = $this->statement->fetchAll(PDO::FETCH_NUM);
        } else {
            $this->stored = $this->affected_rows;
        }

        return $this;
    }

    /**
     * Returns the array as in version 0.9.x
     *
     * @return array|int|mixed
     */
    public function getStored()
    {
        if ($this->statement->columnCount() === 0) {
            return $this->getAffectedRows();
        }

        return $this->fetchAllAssoc();
    }

    /**
     * Checks that a row actually exists
     *
     * @param int $num The number of the row to check on
     * @return bool True if the row exists
     */
    public function hasRow($num)
    {
        return $num >= 0 && $num < $this->num_rows;
    }

    /**
     * Moves the cursor to the selected row
     *
     * @param int $num The number of the row to move the cursor to
     * @return static
     * @throws ResultSetException If the row does not exist
     */
    public function toRow($num)
    {
        if (!$this->hasRow($num)) {
            throw new ResultSetException('The row does not exist.');
        }

        $this->current_row = $num;
        // $this->result->data_seek($num);
        // $this->fetched = $this->statement->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Checks that a next row exists
     *
     * @return bool True if there's another row with a higher index
     */
    public function hasNextRow()
    {
        return $this->current_row < $this->num_rows;
    }

    /**
     * Moves the cursor to the next row
     *
     * @return static $this
     * @throws ResultSetException If the next row does not exist
     */
    public function toNextRow()
    {
        if (!$this->hasNextRow()) {
            throw new ResultSetException('The next row does not exist.');
        }

        if ($this->current_row === null) {
            $this->current_row = 0;
        } else {
            $this->current_row++;
        }

        $this->fetched = $this->statement->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Fetches all the rows as an array of associative arrays
     *
     * @return array|mixed
     */
    public function fetchAllAssoc() {
        if ($this->stored !== null) {
            $result = array();
            foreach ($this->stored as $row_key => $row_value) {
                foreach ($row_value as $col_key => $col_value) {
                    $result[$row_key][$this->fields[$col_key]['name']] = $col_value;
                }
            }

            return $result;
        }

        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all the rows as an array of indexed arrays
     *
     * @return array|mixed|null
     */
    public function fetchAllNum() {
        if ($this->stored !== null) {
            return $this->stored;
        }

        return $this->statement->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * Fetches a row as an associative array
     *
     * @return array
     */
    public function fetchAssoc() {
        if ($this->stored) {
            $row = $this->stored[$this->current_row];
        } else {
            $row = $this->fetched;
        }

        $result = array();
        foreach ($row as $col_key => $col_value) {
            $result[$this->fields[$col_key]['name']] = $col_value;
        }

        return $result;
    }

    /**
     * Fetches a row as an indexed array
     *
     * @return array|null
     */
    public function fetchNum() {
        if ($this->stored) {
            return $this->stored[$this->current_row];
        } else {
            return $this->fetched;
        }
    }

    /**
     * Get the result object returned by PHP's MySQLi
     *
     * @return \Pdostatement
     */
    public function getResultObject()
    {
        return $this->result;
    }

    /**
     * Returns the number of rows affected by the query
     * This will be 0 for SELECT and any query not editing rows
     *
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->affected_rows;
    }

    /**
     * Returns the number of rows in the result set
     *
     * @return int The number of rows in the result set
     */
    public function getCount()
    {
        return $this->num_rows;
    }

    /**
     * Frees the memory from the result
     * Call it after you're done with a result set
     *
     * @return static
     */
    public function freeResult()
    {
        $this->statement->closeCursor();
        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $this->hasRow($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->toRow($offset)->fetchAssoc();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->fetchAssoc();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        if ($this->hasNextRow()) {
            $this->toNextRow();
        } else {
            $this->current_row++;
        }

    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return (int) $this->current_row;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->hasRow($this->current_row);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->toRow(0);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return $this->getCount();
    }
}
