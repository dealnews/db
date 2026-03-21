<?php

namespace DealNews\DB\Util;

/**
 * Value object for raw SQL fragments
 *
 * Use this to inject raw SQL into query builder methods without escaping.
 * Heads-up: Raw SQL is not validated—use with caution.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 */
class Raw {

    /**
     * The raw SQL string
     *
     * @var string
     */
    public string $value = '';

    /**
     * Parameters to bind with this raw SQL fragment
     *
     * @var array<string, mixed>
     */
    public array $params = [];

    /**
     * Creates a new Raw SQL fragment
     *
     * @param string $value  The raw SQL string
     * @param array  $params Parameters to bind (optional)
     */
    public function __construct(string $value, array $params = []) {
        $this->value  = $value;
        $this->params = $params;
    }
}
