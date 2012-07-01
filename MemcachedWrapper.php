<?php

/**
 * A lightweight wrapper around the PHP Memcached extension with three goals:
 *
 *  - You can specify a prefix to prepend to all keys.
 *  - You can use it exactly like a regular Memcached object.
 *  - You can access the cache like an array.
 * 
 * Example:
 *
 * $cache = new MemcachedWrapper('foo');
 * $cache['bar'] = 'x';        // sets 'foobar' to 'x'
 * isset($cache['bar']);       // returns true
 * unset($cache['bar']);       // deletes 'foobar'
 * $cache->set('bar', 'x')     // sets 'foobar' to 'x'
 */
class MemcachedWrapper implements ArrayAccess {

    /**
     * Memcached methods that take key(s) as arguments, and the argument 
     * position of those key(s).
     */
    protected $methods = array(
        'add'           => 0,
        'addByKey'      => 1,
        'append'        => 0,
        'appendByKey'   => 0,
        'cas'           => 1,
        'casByKey'      => 2,
        'decrement'     => 0,
        'delete'        => 0,
        'deleteByKey'   => 1,
        'get'           => 0,
        'getByKey'      => 1,
        'getDelayed'    => 0,
        'getDelayedByKey' => 1,
        'getMulti'      => 0,
        'getMultiByKey' => 1,
        'increment'     => 0,
        'prepend'       => 0,
        'prependByKey'  => 1,
        'replace'       => 0,
        'replaceByKey'  => 1,
        'set'           => 0,
        'setByKey'      => 1,
        'setMulti'      => 0,
        'setMultiByKey' => 1,
    );

    protected $prefix;

    /**
     * The underlying Memcached object, which you can access in order to 
     * override the prefix prepending if you really want.
     */
    public $mc;

    public function __construct($prefix='', $persistent_id=null) {
        $this->prefix = $prefix;

        if ($persistent_id !== null) {
            // not sure if null has the same behavior as not passing it
            $this->mc = new Memcached($persistent_id);
        } else {
            $this->mc = new Memcached();
        }
    }

    public static function __callStatic($name, $args) {
        return call_user_func_array(array($this->mc, $name), $args);
    }

    public function __call($name, $args) {
        // find the position of the argument with key(s), if any
        if (isset($this->methods[$name])) {
            $pos = $this->methods[$name];

            // prepend prefix to key(s)
            if (strpos($name, 'Multi') !== false ||
                strpos($name, 'Delayed' !== false))
            {
                $new = array();
                foreach ($args[$pos] as $k => $v) {
                    $new[$this->prefix . $k] = $v;
                }
                $args[$pos] = $new;
            } else {
                $args[$pos] = $this->prefix . $args[$pos];
            }

        }
        
        return call_user_func_array(array($this->mc, $name), $args);
    }

    public function offsetExists($offset) {
        if ($this->mc->get($this->prefix . $offset)) {
            return true;
        } else if ($this->mc->getResultCode() != Memcached::RES_NOTFOUND) {
            return true;
        } else {
            return false;
        }
    }

    public function offsetGet($offset) {
        return $this->mc->get($this->prefix . $offset);
    }

    public function offsetSet($offset, $value) {
        if ($offset === null) {
            throw new MemcachedWrapperError("Tried to set null offset");
        }
        return $this->mc->set($this->prefix . $offset, $value);
    }

    public function offsetUnset($offset) {
        return $this->mc->delete($this->prefix . $offset);
    }
}

class MemcachedWrapperError extends Exception {}