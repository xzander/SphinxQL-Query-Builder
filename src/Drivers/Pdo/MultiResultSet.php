<?php

namespace Foolz\SphinxQL\Drivers\Pdo;


use Foolz\SphinxQL\Drivers\MultiResultSetInterface;
use Foolz\SphinxQL\Exception\DatabaseException;
use PDOStatement;

class MultiResultSet implements MultiResultSetInterface
{
    /**
     * @var PDOStatement
     */
    public $statement;

    /**
     * @var null|array
     */
    public $stored = null;

    /**
     * @var int
     */
    public $cursor = null;

    /**
     * @param PDOStatement|array|int $statement
     */
    public function __construct($statement)
    {
        if ($statement instanceof PDOStatement) {
            $this->statement = $statement;
        } else {
            $this->stored = $statement;
        }
    }

    public function store()
    {
        if ($this->stored !== null) {
            return $this;
        }

        // don't let users mix storage and pdo cursors
        if ($this->cursor > 0) {
            throw new DatabaseException('The MultiResultSet is using the pdo cursors, store() can\'t fetch all the data');
        }

        $store = array();
        while ($set = $this->getNext()) {
            // this relies on stored being null!
            $store[] = $set->store();
        }

        $this->cursor = null;

        // if we write the array straight to $this->stored it won't be null anymore and functions relying on null will break
        $this->stored = $store;

        return $this;
    }

    public function getStored()
    {
        $this->store();
        return $this->stored;
    }

    public function getNext()
    {
        if ($this->stored !== null) {
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
            }

            if ($this->cursor >= count($this->stored)) {
                return false;
            }

            return $this->stored[$this->cursor];
        } else {
            // the first result is always already loaded
            if ($this->cursor === null) {
                $this->cursor = 0;
            } else {
                $this->cursor++;
                $res = $this->statement->nextRowset();
                if (!$res) {
                    return false;
                }
            }

            return new ResultSet($this->statement);
        }
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
        $this->store();
        return $offset >= 0 && $offset < count($this->stored);
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
        $this->store();
        return $this->stored[$offset];
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
        $this->store();
        return $this->stored[(int) $this->cursor];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->store();
        if ($this->cursor === null) {
            $this->cursor = 0;
        } else {
            $this->cursor++;
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
        return (int) $this->cursor;
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
        $this->store();
        return $this->cursor >= 0 && $this->cursor < count($this->stored);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->store();
        // we actually can't roll this back unless it was stored first
        $this->cursor = null;
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
        $this->store();
        return count($this->stored);
    }
}
