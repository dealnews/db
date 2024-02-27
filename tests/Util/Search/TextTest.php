<?php

namespace DealNews\DB\Tests\Util\Search;

use DealNews\DB\Util\Search\Text;

/**
 * @group unit
 */
class TextTest extends \PHPUnit\Framework\TestCase {

    /**
     * @param array $fields
     * @param array $tokens
     * @param string $expected
     *
     * @dataProvider createLikeStringFromTokensProvider
     */
    public function testCreateLikeStringFromTokens(array $fields, array $tokens, string $expected) {
        $text = new Text();

        $results = $text->createLikeStringFromTokens($fields, $tokens);

        $this->assertEquals($expected, $results);
    }

    public function createLikeStringFromTokensProvider() : array {
        return [
            'one field with two search terms' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => 'foo',
                    ],
                    [
                        'token' => 'bar',
                        'join'  => 'AND',
                    ],
                ],
                'expected' => '(search_thing LIKE \'%foo%\' AND search_thing LIKE \'%bar%\')',
            ],

            'one field with two "or" search terms' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => 'foo',
                    ],
                    [
                        'token' => 'bar',
                        'join'  => 'OR',
                    ],
                ],
                'expected' => '(search_thing LIKE \'%foo%\' OR search_thing LIKE \'%bar%\')',
            ],

            'one field with two "or" search terms in parens' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => [
                            [
                                'token' => 'foo',
                            ],
                            [
                                'token' => 'bar',
                                'join'  => 'OR',
                            ],
                        ],
                    ],
                ],
                'expected' => '((search_thing LIKE \'%foo%\' OR search_thing LIKE \'%bar%\'))',
            ],

            'one field with two "or" search terms with "starts with"' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => [
                            [
                                'token' => 'foo%',
                            ],
                            [
                                'token' => 'bar',
                                'join'  => 'OR',
                            ],
                        ],
                    ],
                ],
                'expected' => '((search_thing LIKE \'foo%\' OR search_thing LIKE \'%bar%\'))',
            ],

            'one field with two "or" search terms. First "starts with", second equals' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => [
                            [
                                'token' => 'foo%',
                            ],
                            [
                                'token' => '^bar$',
                                'join'  => 'OR',
                            ],
                        ],
                    ],
                ],
                'expected' => '((search_thing LIKE \'foo%\' OR search_thing = \'bar\'))',
            ],

            'one field with one term with a dash in it' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => 'foo-bar',
                    ],
                ],
                'expected' => '(search_thing LIKE \'%foo-bar%\')',
            ],

            'one field with two "or" search terms with a third "not" term' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => [
                            [
                                'token' => 'foo%',
                            ],
                            [
                                'token' => 'bar',
                                'join'  => 'OR',
                            ],
                        ],
                    ],
                    [
                        'token'    => 'baz',
                        'join'     => 'AND',
                        'modifier' => 'NOT',
                    ],
                ],
                'expected' => '((search_thing LIKE \'foo%\' OR search_thing LIKE \'%bar%\') AND search_thing NOT LIKE \'%baz%\')',
            ],

            'two fields with one term' => [
                'fields' => ['search_thing1', 'search_thing2'],
                'tokens' => [
                    [
                        'token' => 'foo',
                    ],
                ],
                'expected' => '(search_thing1 LIKE \'%foo%\' OR search_thing2 LIKE \'%foo%\')',
            ],

            'two fields with two terms' => [
                'fields' => ['search_thing1', 'search_thing2'],
                'tokens' => [
                    [
                        'token' => 'foo',
                    ],
                    [
                        'token' => 'bar',
                        'join'  => 'AND',
                    ],
                ],
                'expected' => '((search_thing1 LIKE \'%foo%\' OR search_thing2 LIKE \'%foo%\') AND (search_thing1 LIKE \'%bar%\' OR search_thing2 LIKE \'%bar%\'))',
            ],

            'two fields with two "or" terms with a third "not" term' => [
                'fields' => ['search_thing1', 'search_thing2'],
                'tokens' => [
                    [
                        'token' => [
                            [
                                'token' => 'foo',
                            ],
                            [
                                'token' => 'bar',
                                'join'  => 'OR',
                            ],
                        ],
                    ],
                    [
                        'token'    => 'baz',
                        'join'     => 'AND',
                        'modifier' => 'NOT',
                    ],
                ],
                'expected' => '(((search_thing1 LIKE \'%foo%\' OR search_thing1 LIKE \'%bar%\') OR (search_thing2 LIKE \'%foo%\' OR search_thing2 LIKE \'%bar%\')) AND (search_thing1 NOT LIKE \'%baz%\' AND search_thing2 NOT LIKE \'%baz%\'))',
            ],

            'one field with a term with a "not" term group' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => 'foo',
                    ],
                    [
                        'token'    => [
                            [
                                'token' => 'bar',
                            ],
                            [
                                'token' => 'baz',
                                'join'  => 'OR',
                            ],
                        ],
                        'join'     => 'AND',
                        'modifier' => 'NOT',
                    ],
                ],
                'expected' => '(search_thing LIKE \'%foo%\' AND NOT (search_thing LIKE \'%bar%\' OR search_thing LIKE \'%baz%\'))',
            ],

            'one field with one term with a percent in the middle' => [
                'fields' => ['search_thing'],
                'tokens' => [
                    [
                        'token' => 'foo%bar',
                    ],
                ],
                'expected' => '(search_thing LIKE \'%foo%bar%\')',
            ],
        ];
    }
}
