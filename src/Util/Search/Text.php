<?php

namespace DealNews\DB\Util\Search;

/**
 * Creates an SQL LIKE string from search text
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     \DealNews\DB
 *
 * @phan-suppress PhanUnreferencedClass
 */
class Text {

    /**
     * Singleton
     *
     * @return self
     */
    public static function init() {
        static $inst;

        if (empty($inst)) {
            $class = get_called_class();
            $inst  = new $class();
        }

        return $inst;
    }

    /**
     * Creates a LIKE clause for a query
     *
     * @param   array   $fields     Fields to be searched
     * @param   string  $string     The search string provided by the user
     *
     * @return  string
     */
    public function createLikeString(array $fields, string $string) : string {
        $string = trim($string);

        $like_string = '';

        if (!empty($string)) {

            $tokens = $this->tokenizeString($string);

            if (!empty($tokens)) {
                $like_string = $this->createLikeStringFromTokens($fields, $tokens);
            }
        }

        return $like_string;
    }

    /**
     * Creates a LIKE clause for a query using a token array
     *
     * @param   array   $fields     Fields to be searched
     * @param   array   $tokens     Array from \DealNews\Utilities\Search\Text::tokenizeString
     *
     * @return  string
     */
    public function createLikeStringFromTokens(array $fields, array $tokens) : string {
        $like_string = '';

        foreach ($tokens as $token) {
            if (!empty($token['join'])) {
                $like_string .= " $token[join]";
            }

            $field_clauses = [];

            foreach ($fields as $field) {
                $field_clause = '';

                if (is_array($token['token'])) {
                    if (!empty($token['modifier'])) {
                        $field_clause .= " $token[modifier]";
                    }

                    $field_clause .= ' ' . $this->createLikeStringFromTokens([$field], $token['token']);
                } else {
                    $tok = $token['token'];

                    $comp = 'LIKE';

                    if (!empty($token['modifier'])) {
                        $comp = "$token[modifier] $comp";
                    }

                    if (mb_substr($tok, 0, 1) != '%' && mb_substr($tok, -1) != '%') {
                        $has_percent = false;

                        if (mb_substr($tok, 0, 1) == '^') {
                            $tok = mb_substr($tok, 1);
                        } else {
                            $has_percent = true;
                            $tok         = "%$tok";
                        }

                        if ($tok[mb_strlen($tok) - 1] == '$') {
                            $tok = mb_substr($tok, 0, -1);
                        } else {
                            $has_percent = true;
                            $tok         = "$tok%";
                        }

                        if (!$has_percent) {
                            if (!empty($token['modifier']) && $token['modifier'] == 'NOT') {
                                $comp = '<>';
                            } else {
                                $comp = '=';
                            }
                        }
                    } else {

                        // strip off the ^ and $ for already wildcarded strings
                        // as it may not be intuitive otherwise

                        if (mb_substr($tok, 0, 1) == '^') {
                            $tok = mb_substr($tok, 1);
                        }

                        if ($tok[mb_strlen($tok) - 1] == '$') {
                            $tok = mb_substr($tok, 0, -1);
                        }
                    }

                    $field_clause .= " $field $comp '" . str_replace("'", "\\'", $tok) . "'";
                }

                $field_clauses[] = trim($field_clause);
            }

            if (count($field_clauses) > 1) {
                $like_string .= ' ';

                if (count($tokens) > 1) {
                    $like_string .= '(';
                }

                if (!empty($token['modifier']) && $token['modifier'] == 'NOT') {
                    $joiner = 'AND';
                } else {
                    $joiner = 'OR';
                }

                $like_string .= implode(" $joiner ", $field_clauses);

                if (count($tokens) > 1) {
                    $like_string .= ')';
                }
            } else {
                $like_string .= ' ' . current($field_clauses);
            }
        }

        $like_string = trim($like_string);

        if (!empty($like_string)) {
            $like_string = '(' . $like_string . ')';
        }

        return $like_string;
    }

    /**
     * Tokenizes a string for use in filtering
     *
     * @param   string  $string     The string provided by the user
     *
     * @return  array
     */
    public function tokenizeString(string $string) : array {
        $string = trim($string);

        $tokens      = [];
        $paren_depth = 0;
        $buffer      = '';
        $exact       = false;
        $in_quotes   = false;
        $last_char   = null;

        $join = '';

        $modifier = '';

        $x = 0;

        $len = mb_strlen($string);

        do {
            $char = null;

            if ($x < $len) {
                $char = mb_substr($string, $x, 1);
            }

            if ($in_quotes) {
                if ($char == '"') {
                    if ($last_char != '\\') {
                        $in_quotes = false;
                        $exact     = true;
                    } else {
                        $buffer = mb_substr($buffer, 0, -1);
                        $buffer .= $char;
                    }
                } else {
                    $buffer .= $char;
                }
            } elseif ($paren_depth > 0) {
                if ($char == '(') {
                    $buffer .= $char;
                    $paren_depth++;
                } elseif ($char == ')') {
                    $paren_depth--;
                    if ($paren_depth == 0) {
                        if (mb_strlen($buffer) > 0) {
                            $buffer = $this->tokenizeString($buffer);
                        }
                    } else {
                        $buffer .= $char;
                    }
                } else {
                    $buffer .= $char;
                }
            } else {
                $is_break = $x       >= $len;
                $is_end   = ($x + 1) >= $len;

                if (!$is_end && (
                    $char     == ' '     ||
                        $char == ','     ||
                        $char == '('     ||
                        (
                            $char == '"' &&
                            (
                                is_null($last_char) ||
                                $last_char == ','   ||
                                $last_char == '('   ||
                                $last_char == ' '   ||
                                $last_char == '-'
                            )
                        ) ||
                        (
                            $char == '-' &&
                            (
                                is_null($last_char) ||
                                $last_char == ' '
                            )
                        )
                )
                ) {
                    $is_break = true;

                    /**
                     * Quotes mode should only follow a space, comma, or paren open
                     */
                    if ($char == '"') {
                        /**
                         * only put us in quotes mode when there there is at
                         * least another bare quote somewhere in the string
                         */
                        $quote_count     = mb_substr_count(mb_substr($string, $x + 1), '"');
                        $esc_quote_count = mb_substr_count(mb_substr($string, $x + 1), '\\"');
                        if ($quote_count - $esc_quote_count > 0) {
                            $in_quotes = true;
                        } else {
                            $is_break = $x >= $len;
                        }
                    } elseif ($char == '(') {
                        $paren_depth++;
                    }
                }

                if ($is_break || is_array($buffer)) {
                    if (is_array($buffer) || mb_strlen($buffer) > 0) {
                        if (!is_array($buffer)) {
                            $buffer = trim($buffer);
                        }

                        $new_token = [
                            'token' => $buffer,
                        ];
                        if (!empty($join)) {
                            $new_token['join'] = $join;
                        }
                        if (!empty($modifier)) {
                            $new_token['modifier'] = $modifier;
                        }

                        if (!empty($exact)) {
                            $new_token['exact'] = $exact;
                        }

                        $tokens[] = $new_token;

                        $buffer = '';

                        $exact = false;
                    }

                    // reset on any break
                    if (!empty($tokens)) {
                        if ($char == ',') {
                            $join = 'OR';
                        } else {
                            $join = 'AND';
                        }
                    } else {
                        $join = '';
                    }

                    if ($char != '(' && $char != '"') {
                        if ($char == '-') {
                            $modifier = 'NOT';
                        } else {
                            $modifier = '';
                        }
                    }
                }

                if (!$is_break) {
                    $buffer .= $char;
                }
            }

            $last_char = $char;

            $x++;
        } while ($x <= $len);

        return $tokens;
    }
}
