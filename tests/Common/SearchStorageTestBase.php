<?php

namespace Shopware\StorageTests\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Exception\NotSupportedByEngine;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Schema\Translation\TranslatedString;
use Shopware\Storage\Common\Schema\Translation\TranslatedText;
use Shopware\Storage\Common\Search\Group;
use Shopware\Storage\Common\Search\Query;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\Search\SearchAware;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\StorageTests\Common\Schema\Category;
use Shopware\StorageTests\Common\Schema\Product;

abstract class SearchStorageTestBase extends TestCase
{
    abstract public function getStorage(): SearchAware&Storage;

    /**
     * @return string[]|null
     */
    public function match(string $case): ?array
    {
        return null;
    }

    public static function debugging(): \Generator
    {
        yield 'clothes: red cotton shirt' => [
            'search' => new Search(
                group: new Group(queries: [
                    new Group(queries: [
                        new Query(field: 'ean', term: 'red'),
                        new Query(field: 'number', term: 'red'),
                    ]),
                    new Group(queries: [
                        new Query(field: 'ean', term: 'cotton'),
                        new Query(field: 'number', term: 'cotton'),
                    ]),
                    new Group(queries: [
                        new Query(field: 'ean', term: 'shirt'),
                        new Query(field: 'number', term: 'shirt'),
                    ]),
                ], hits: 2),
            ),
            'input' => new Documents(elements: [
                new Product(
                    key: 'dug-shirt',
                    ean: 'Funny dug classic T-Shirt',
                    number: 'dug-shirt',
                    name: new TranslatedString(['en' => 'Red cotton shirt', 'de' => 'Rotes Baumwollshirt']),
                    keywords: ['red', 'shirt', 'cotton'],
                    mainCategory: new Category(
                        ean: 'red',
                        name: new TranslatedString(['en' => 'Red', 'de' => 'Rot'])
                    ),
                    categories: [
                        new Category(
                            ean: 'red',
                            name: new TranslatedString(['en' => 'Red', 'de' => 'Rot'])
                        ),
                        new Category(
                            ean: 'cotton',
                            name: new TranslatedString(['en' => 'Cotton', 'de' => 'Baumwolle'])
                        ),
                    ]
                ),
                new Product(key: 'red-shirt', ean: 'Red cotton shirt', number: 'red-shirt'),
                new Product(key: 'blue-shirt', ean: 'Blue cotton shirt', number: 'blue-shirt'),
                new Product(key: 'green-shirt', ean: 'Green cotton', number: 'green-shirt'),
                new Product(key: 'jeans', ean: 'jeans', number: 'jeans'),
                new Product(key: 'funny-shirt', ean: 'Funny prints selection', number: 'funny-shirt'),
            ]),
            'expected' => ['red-shirt', 'blue-shirt', 'green-shirt'],
        ];
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('specialCharacterCases')]
    public function testDebug(Search $search, Documents $input, array $expected): void
    {
        $this->testSearch(
            search: $search,
            input: $input,
            expected: $expected
        );
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('wheelCases')]
    #[DataProvider('hardwareCases')]
    #[DataProvider('clothesCases')]
    #[DataProvider('termTypeCases')]
    #[DataProvider('groupCases')]
    #[DataProvider('fieldTypeCases')]
    #[DataProvider('specialCharacterCases')]
    final public function testSearch(Search $search, Documents $input, array $expected): void
    {
        $storage = $this->getStorage();

        $storage->store($input);

        try {
            $loaded = $storage->search(
                search: $search,
                criteria: new Criteria(),
                context: new StorageContext(languages: ['en', 'de'])
            );
        } catch (NotSupportedByEngine $e) {
            static::markTestIncomplete($e->getMessage());
        }

        $rewrite = $this->match((string) $this->dataName());

        $expected = $rewrite ?? $expected;

        $matches = $loaded->keys();
        sort($expected);
        sort($matches);

        static::assertEquals($expected, $matches);
    }

    public static function specialCharacterCases(): \Generator
    {
        yield 'Umlauts' => [
            'search' => new Search(
                group: new Group(
                    queries: [new Query(field: 'ean', term: 'über')],
                    hits: 1
                )
            ),
            'input' => new Documents([
                new Product(key: 'über', ean: 'über'),
                new Product(key: 'ueber', ean: 'ueber'),
                new Product(key: 'uuber', ean: 'uber'),
                new Product(key: 'drüber', ean: 'drüber'),
            ]),
            'expected' => ['über', 'uuber'],
        ];

        //        yield 'Please provide more cases' => [];
    }

    public static function clothesCases(): \Generator
    {
        yield 'clothes: red cotton shirt' => [
            'search' => new Search(
                group: new Group(queries: [
                    new Group(queries: [
                        new Query(field: 'ean', term: 'red'),
                        new Query(field: 'keywords', term: 'red'),
                        new Query(field: 'categories.ean', term: 'red'),
                    ], hits: 1),
                    new Group(queries: [
                        new Query(field: 'ean', term: 'cotton'),
                        new Query(field: 'keywords', term: 'cotton'),
                        new Query(field: 'categories.ean', term: 'cotton'),
                    ], hits: 1),
                    new Group(queries: [
                        new Query(field: 'ean', term: 'shirt'),
                        new Query(field: 'keywords', term: 'shirt'),
                        new Query(field: 'categories.ean', term: 'shirt'),
                    ], hits: 1),
                ], hits: 2),
            ),
            'input' => new Documents(elements: [
                new Product(key: 'dug-shirt', ean: 'Funny dug classic T-Shirt', categories: [new Category(ean: 'red'), new Category(ean: 'cotton')]),
                new Product(key: 'red-shirt', ean: 'Red cotton shirt', categories: []),
                new Product(key: 'blue-shirt', ean: 'Blue cotton shirt', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'green-shirt', ean: 'Green shirt', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'jeans', ean: 'jeans', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'funny-shirt', ean: 'Funny prints selection', keywords: ['red', 'shirt', 'cotton']),
            ]),
            'expected' => ['red-shirt', 'dug-shirt', 'blue-shirt', 'funny-shirt', 'green-shirt'],
        ];

        yield 'clothes: full term provided' => [
            'search' => new Search(new Group(queries: [
                new Query(field: 'ean', term: 'Cotton shirt'),
                new Query(field: 'categories.ean', term: 'Cotton shirt'),
                new Query(field: 'keywords', term: 'Cotton shirt'),
            ])),
            'input' => new Documents(elements: [
                new Product(key: 'dug-shirt', ean: 'Funny dug classic T-Shirt', categories: [new Category(ean: 'red'), new Category(ean: 'cotton')]),
                new Product(key: 'red-shirt', ean: 'Red cotton shirt', categories: []),
                new Product(key: 'blue-shirt', ean: 'Blue cotton shirt', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'green-shirt', ean: 'Green shirt', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'jeans', ean: 'jeans', categories: [new Category(ean: 'cotton')]),
                new Product(key: 'funny-shirt', ean: 'Funny prints selection', keywords: ['red', 'shirt', 'cotton']),
            ]),
            'expected' => ['blue-shirt', 'dug-shirt', 'funny-shirt', 'green-shirt', 'jeans', 'red-shirt'],
        ];
    }

    public static function hardwareCases(): \Generator
    {
        yield 'hardware, number+ssd case' => [
            'search' => new Search(group: new Group([
                new Query(field: 'ean', term: '164 SSD'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164'],
        ];
        yield 'hardware, ssd+number case' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'SSD 164'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164'],
        ];
        yield 'hardware, ssd+number+gb case' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'SSD 164GB'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164'],
        ];
        yield 'hardware, number+gb+ssd case' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: '164GB SSD'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164'],
        ];
        yield 'hardware, number+gb case' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: '164GB'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164', 'hdd-164'],
        ];
        yield 'hardware, number case' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: '164'),
            ])),
            'input' => new Documents([
                new Product(key: 'ssd-164', ean: '164GB SSD'),
                new Product(key: 'hdd-164', ean: '164GB HDD'),
            ]),
            'expected' => ['ssd-164', 'hdd-164'],
        ];

        //        yield 'Please provide more cases (graphic cards, main boards, power supply, ram sticks)' => [];
    }

    public static function wheelCases(): \Generator
    {
        $tires = [
            new Product(key: 'KV1DC9519255Z2-B1', number: 'KV1DC9519255Z2-B1', ean: 'MB KV1 DC 9,5x19" 5x120.65 ET25.0 70.3 5Z2 schwarz seidenmatt'),
            new Product(key: 'KV1DC1022355MF-B4', number: 'KV1DC1022355MF-B4', ean: 'MB KV1 DC 10,0x22" 5x127 ET35.0 71.55 5MF schwarz glänzend poliert'),
            new Product(key: 'KV1S9521385CZA-B3', number: 'KV1S9521385CZA-B3', ean: 'MB KV1S C 9,5x21" 5x114.3 ET38.0 75.0 5CZA schwarz glänzend'),
            new Product(key: 'KV1DC1022455B2-S2', number: 'KV1DC1022455B2-S2', ean: 'MB KV1 DC 10,0x22" 5x112 ET45.0 75.0 5B2 silber glänzend'),
            new Product(key: 'KV1DC1122355B2-B1', number: 'KV1DC1122355B2-B1', ean: 'MB KV1 DC 11,0x22" 5x112 ET35.0 75.0 5B2 schwarz seidenmatt'),
            new Product(key: 'KV1S8021355CZ-GO3', number: 'KV1S8021355CZ-GO3', ean: 'MB KV1S C 8,0x21" 5x114.3 ET35.0 75.0 5CZ gold glänzend'),
            new Product(key: 'KV1DC10520185PZ-B3', number: 'KV1DC10520185PZ-B3', ean: 'MB KV1 DC 10,5x20" 5x112 ET18.0 75.0 5PZ schwarz glänzend'),
            new Product(key: 'KV19020345C9-B2', number: 'KV19020345C9-B2', ean: 'MB KV1 9,0x20" 5x115 ET34.0 75.0 5C9 schwarz matt poliert'),
            new Product(key: 'KV1DC9519305A-NP', number: 'KV1DC9519305A-NP', ean: 'MB KV1 DC 9,5x19" 5x100 ET30.0 67.1 5A unlackiert'),
            new Product(key: 'KV1SDC10521185RZ', number: 'KV1SDC10521185RZ', ean: 'MB KV1S DC 10,5x21" 5x112 ET18.0 75.0 5RZ'),
        ];

        yield 'wheel, 9,5x19 5x120' => [
            'search' => new Search(
                group: new Group([
                    new Query(field: 'ean', term: '9,5x19 5x120'),
                ])
            ),
            'input' => new Documents($tires),
            'expected' => ['KV1DC9519255Z2-B1'],
        ];

        yield 'wheel, 9,5x19"' => [
            'search' => new Search(group: new Group([new Query(field: 'ean', term: '9,5x19"')])),
            'input' => new Documents($tires),
            'expected' => ['KV1DC9519305A-NP', 'KV1DC9519255Z2-B1'],
        ];

        yield 'wheel, KV1S' => [
            'search' => new Search(
                group: new Group([
                    new Query(field: 'number', term: 'KV1S'),
                    new Query(field: 'ean', term: 'KV1S'),
                ])
            ),
            'input' => new Documents($tires),
            'expected' => ['KV1SDC10521185RZ', 'KV1S9521385CZA-B3', 'KV1S8021355CZ-GO3'],
        ];

        yield 'wheel, 9,5 KV1S' => [
            'search' => new Search(group: new Group([
                new Query(field: 'number', term: '9,5 KV1S'),
                new Query(field: 'ean', term: '9,5 KV1S'),
            ])),
            'input' => new Documents($tires),
            'expected' => ['KV1S9521385CZA-B3'],
        ];
    }

    public static function termTypeCases(): \Generator
    {
        yield 'single term, match' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Mega-Innovation 5000'),
            ]),
            'expected' => ['mega-innovation', 'innovation-hub'],
        ];

        yield 'single term, prefix phrase' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'innovation-9050'),
                new Product(key: 'electro-pioneer', ean: 'tech-pro-500'),
                new Product(key: 'mega-innovation', ean: 'mega-innovation-5000'),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];

        yield 'single term, suffix phrase' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: '9050-innovation'),
                new Product(key: 'electro-pioneer', ean: '500-tech-pro'),
                new Product(key: 'mega-innovation', ean: 'mega-innovation-5000'),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];

        yield 'single term, term phrase' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'inno'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'innovation-9050'),
                new Product(key: 'electro-pioneer', ean: 'tech-pro-500'),
                new Product(key: 'mega-innovation', ean: 'mega-innovation-5000'),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];

        yield 'single term, special characters' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'tech-pro-500'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Innovation-pro-500'),
                new Product(key: 'electro-pioneer', ean: 'tech-pro-500'),
                new Product(key: 'mega-innovation', ean: 'mega-innovation-5000'),
            ]),
            'expected' => ['electro-pioneer', 'innovation-hub'],
        ];

        yield 'multi term, match' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'Innovation Hub'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Innovation Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Hub-Mega-Innovation 5000'),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];

        yield 'compound word' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'innovation-hub'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Innovation Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Hub-Mega-Innovation 5000'),
            ]),
            // should be interpreted as "one word" and gets analyzed internally to match also partial words
            'expected' => ['innovation-hub', 'mega-innovation', 'electro-pioneer'],
        ];
    }

    public static function groupCases(): \Generator
    {
        yield 'group-minimum-1' => [
            'search' => new Search(
                new Group([
                    new Query(field: 'ean', term: 'Innovation'),
                    new Query(field: 'ean', term: 'Hub'),
                ]),
            ),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Innovation Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Mega-Innovation 5000'),
            ]),
            'expected' => ['innovation-hub', 'electro-pioneer', 'mega-innovation'],
        ];

        yield 'group-minimum-2' => [
            'search' => new Search(
                new Group([
                    new Query(field: 'ean', term: 'Innovation', boost: 2.0),
                    new Query(field: 'ean', term: 'Hub', boost: 1.0),
                ], hits: 2),
            ),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Innovation Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Hub-Mega-Innovation 5000'),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];

        yield 'nested-group' => [
            'search' => new Search(
                new Group([
                    new Group([
                        new Query(field: 'ean', term: 'Innovation'),
                        new Query(field: 'ean', term: 'Hub'),
                    ]),
                    new Group([
                        new Query(field: 'number', term: 'Innovation'),
                        new Query(field: 'number', term: 'Hub'),
                    ]),
                ], hits: 2),
            ),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050', number: 'innovation-hub',),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Innovation Pioneer', number: 'electro-pioneer',),
                new Product(key: 'mega-innovation', ean: 'Hub-Mega 5000', number: 'mega-innovation',),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation'],
        ];
    }

    public static function fieldTypeCases(): \Generator
    {
        yield 'string-field support' => [
            'search' => new Search(new Group([
                new Query(field: 'ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', ean: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', ean: 'ElectroTechPro Pioneer'),
                new Product(key: 'mega-innovation', ean: 'Mega-Innovation 5000'),
                new Product(key: 'awesome-innovation', ean: 'Awesome ("innovation") value'),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'text-field support' => [
            // field => comment
            'search' => new Search(new Group([
                new Query(field: 'comment', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', comment: 'Electronics Innovation Hub 9050'),
                new Product(key: 'electro-pioneer', comment: 'ElectroTechPro Pioneer'),
                new Product(key: 'mega-innovation', comment: 'Mega-Innovation 5000'),
                new Product(key: 'awesome-innovation', comment: 'Awesome ("innovation") value'),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'string-list field support' => [
            // field => keywords
            'search' => new Search(new Group([
                new Query(field: 'keywords', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', keywords: ['Electronics', 'Innovation', 'Hub', '9050']),
                new Product(key: 'electro-pioneer', keywords: ['ElectroTechPro', 'Pioneer']),
                new Product(key: 'mega-innovation', keywords: ['Mega', 'Innovation', '5000']),
                new Product(key: 'awesome-innovation', keywords: ['Awesome', '("innovation")', 'value']),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'translated-string support' => [
            // field => name (en > de)
            'search' => new Search(new Group([
                new Query(field: 'name', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', name: new TranslatedString(['en' => 'Innovation Hub'])),
                new Product(key: 'electro-pioneer', name: new TranslatedString(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier'])),
                new Product(key: 'mega-innovation', name: new TranslatedString(['de' => 'Mega-Innovation 5000'])),
                new Product(key: 'awesome-innovation', name: new TranslatedString(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value'])),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];

        yield 'translated-text support' => [
            // field => description
            // field => name (en > de)
            'search' => new Search(new Group([
                new Query(field: 'description', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', description: new TranslatedText(['en' => 'Innovation Hub'])),
                new Product(key: 'electro-pioneer', description: new TranslatedText(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier'])),
                new Product(key: 'mega-innovation', description: new TranslatedText(['de' => 'Mega-Innovation 5000'])),
                new Product(key: 'awesome-innovation', description: new TranslatedText(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value'])),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];

        yield 'object->string support' => [
            // field => mainCategory.ean
            'search' => new Search(new Group([
                new Query(field: 'mainCategory.ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', mainCategory: new Category(ean: 'Electronics Innovation Hub 9050')),
                new Product(key: 'electro-pioneer', mainCategory: new Category(ean:'ElectroTechPro Pioneer')),
                new Product(key: 'mega-innovation', mainCategory: new Category(ean:'Mega-Innovation 5000')),
                new Product(key: 'awesome-innovation', mainCategory: new Category(ean:'Awesome ("innovation") value')),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object->text support' => [
            // field => mainCategory.comment
            'search' => new Search(new Group([
                new Query(field: 'mainCategory.comment', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub',mainCategory: new Category(comment: 'Electronics Innovation Hub 9050')),
                new Product(key: 'electro-pioneer',mainCategory: new Category(comment: 'ElectroTechPro Pioneer')),
                new Product(key: 'mega-innovation',mainCategory: new Category(comment: 'Mega-Innovation 5000')),
                new Product(key: 'awesome-innovation',mainCategory: new Category(comment: 'Awesome ("innovation") value')),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object->string-list field support' => [
            // field => mainCategory.keywords
            'search' => new Search(new Group([
                new Query(field: 'mainCategory.keywords', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub',mainCategory: new Category(keywords: ['Electronics', 'Innovation', 'Hub', '9050'])),
                new Product(key: 'electro-pioneer',mainCategory: new Category(keywords: ['ElectroTechPro', 'Pioneer'])),
                new Product(key: 'mega-innovation',mainCategory: new Category(keywords: ['Mega', 'Innovation', '5000'])),
                new Product(key: 'awesome-innovation',mainCategory: new Category(keywords: ['Awesome', '("innovation")', 'value'])),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object->translated-string support' => [
            // field => mainCategory.name
            'search' => new Search(new Group([
                new Query(field: 'mainCategory.name', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub',mainCategory: new Category(name: new TranslatedString(['en' => 'Innovation Hub']))),
                new Product(key: 'electro-pioneer',mainCategory: new Category(name: new TranslatedString(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier']))),
                new Product(key: 'mega-innovation',mainCategory: new Category(name: new TranslatedString(['de' => 'Mega-Innovation 5000']))),
                new Product(key: 'awesome-innovation',mainCategory: new Category(name: new TranslatedString(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value']))),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];

        yield 'object->translated->text support' => [
            // field => mainCategory.description
            'search' => new Search(new Group([
                new Query(field: 'mainCategory.description', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub',mainCategory: new Category(description: new TranslatedText(['en' => 'Innovation Hub']))),
                new Product(key: 'electro-pioneer',mainCategory: new Category(description: new TranslatedText(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier']))),
                new Product(key: 'mega-innovation',mainCategory: new Category(description: new TranslatedText(['de' => 'Mega-Innovation 5000']))),
                new Product(key: 'awesome-innovation',mainCategory: new Category(description: new TranslatedText(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value']))),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];

        yield 'object-list->string support' => [
            // field => categories.ean
            'search' => new Search(new Group([
                new Query(field: 'categories.ean', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', categories: [new Category(ean: 'Electronics Innovation Hub 9050')]),
                new Product(key: 'electro-pioneer', categories: [new Category(ean: 'ElectroTechPro Pioneer')]),
                new Product(key: 'mega-innovation', categories: [new Category(ean: 'Mega-Innovation 5000')]),
                new Product(key: 'awesome-innovation', categories: [new Category(ean: 'Awesome ("innovation") value')]),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object-list->text support' => [
            // field => categories.comment
            'search' => new Search(new Group([
                new Query(field: 'categories.comment', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', categories: [new Category(comment: 'Electronics Innovation Hub 9050')]),
                new Product(key: 'electro-pioneer', categories: [new Category(comment: 'ElectroTechPro Pioneer')]),
                new Product(key: 'mega-innovation', categories: [new Category(comment: 'Mega-Innovation 5000')]),
                new Product(key: 'awesome-innovation', categories: [new Category(comment: 'Awesome ("innovation") value')]),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object-list->string-list field support' => [
            // field => categories.keywords
            'search' => new Search(new Group([
                new Query(field: 'categories.keywords', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', categories: [new Category(keywords: ['Electronics', 'Innovation', 'Hub', '9050'])]),
                new Product(key: 'electro-pioneer', categories: [new Category(keywords: ['ElectroTechPro', 'Pioneer'])]),
                new Product(key: 'mega-innovation', categories: [new Category(keywords: ['Mega', 'Innovation', '5000'])]),
                new Product(key: 'awesome-innovation', categories: [new Category(keywords: ['Awesome', '("innovation")', 'value'])]),
            ]),
            'expected' => ['mega-innovation', 'awesome-innovation', 'innovation-hub'],
        ];

        yield 'object-list->translated-string support' => [
            // field => categories.name
            'search' => new Search(new Group([
                new Query(field: 'categories.name', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', categories: [new Category(name: new TranslatedString(['en' => 'Innovation Hub']))]),
                new Product(key: 'electro-pioneer', categories: [new Category(name: new TranslatedString(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier']))]),
                new Product(key: 'mega-innovation', categories: [new Category(name: new TranslatedString(['de' => 'Mega-Innovation 5000']))]),
                new Product(key: 'awesome-innovation', categories: [new Category(name: new TranslatedString(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value']))]),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];

        yield 'object-list->translated->text support' => [
            // field => categories.description
            'search' => new Search(new Group([
                new Query(field: 'categories.description', term: 'Innovation'),
            ])),
            'input' => new Documents([
                new Product(key: 'innovation-hub', categories: [new Category(description: new TranslatedText(['en' => 'Innovation Hub']))]),
                new Product(key: 'electro-pioneer', categories: [new Category(description: new TranslatedText(['en' => 'ElectroTechPro Pioneer', 'de' => 'ElektroTechPro Pionier']))]),
                new Product(key: 'mega-innovation', categories: [new Category(description: new TranslatedText(['de' => 'Mega-Innovation 5000']))]),
                new Product(key: 'awesome-innovation', categories: [new Category(description: new TranslatedText(['en' => 'Awesome value', 'de' => 'Awesome ("innovation") value']))]),
            ]),
            'expected' => ['innovation-hub', 'mega-innovation', 'awesome-innovation'],
        ];
    }
}
