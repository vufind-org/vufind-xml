<?php

/**
 * XML Handling Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) 2026 University of Helsinki, National library of Finland.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFindXml
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */

namespace VuFindXmlTest;

use PHPUnit\Framework\Attributes\DataProvider;
use VuFindXml\Notation;
use VuFindXml\XmlDoc;

/**
 * XML Handling Test Class.
 *
 * @category VuFindXml
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */
class XmlDocTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test XML creation with existing namespaces.
     *
     * @return void
     */
    public function testXmlWithNs(): void
    {
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertXmlStringEqualsXmlString(
            $xmlStr,
            $xml->toXML(2)
        );
    }

    /**
     * Test creating XML without namespaces.
     *
     * @return void
     */
    public function testXmlWithoutNs(): void
    {
        $xmlStr = $this->getFixture('xml-without-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-only-xsi-ns.xml'),
            $xml->toXML(2)
        );
    }

    /**
     * Test adding of namespace when creating XML.
     *
     * @return void
     */
    public function testXmlAddNs(): void
    {
        $xmlStr = $this->getFixture('xml-without-ns.xml');
        // Adjust for the fact that adding namespaces won't magically handle xml:lang properly:
        $expected = str_replace('xml:lang', 'lido:lang', $this->getFixture('xml-with-ns.xml'));
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $xml->setDefaultNamespace('http://www.lido-schema.org', 'lido');
        $this->assertXmlStringEqualsXmlString(
            $expected,
            $xml->toXML(2)
        );
    }

    /**
     * Test import/export.
     *
     * @return void
     */
    public function testImportExport(): void
    {
        $xmlStr = $this->getFixture('xml-with-multiple-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $xml2 = new XmlDoc();
        $xml2->import($xml->export());
        $this->assertXmlStringEqualsXmlString(
            $xmlStr,
            $xml2->toXML(2)
        );

        $ns = 'http://www.lido-schema.org';
        $xml2->import($xml->export($xml->first(path: "{{$ns}}lido/{{$ns}}category")));
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-multiple-ns-category.xml'),
            $xml2->toXML(2)
        );
    }

    /**
     * Test toXML.
     *
     * @return void
     */
    public function testToXML(): void
    {
        $ns = 'http://www.lido-schema.org';
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertXmlStringEqualsXmlString(
            $xmlStr,
            $xml->toXML()
        );

        $node = $xml->first(
            path: "{{$ns}}lido/{{$ns}}descriptiveMetadata/{{$ns}}objectIdentificationWrap/{{$ns}}titleWrap"
        );
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-ns-titlewrap.xml'),
            $xml->toXML(2, node: $node)
        );

        $nodes = $xml->all(
            path: "{{$ns}}lido/{{$ns}}descriptiveMetadata/{{$ns}}objectIdentificationWrap/{{$ns}}titleWrap/"
            . "{{$ns}}titleSet"
        );
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-ns-titleset.xml'),
            $xml->toXML(2, node: $nodes[1])
        );
    }

    /**
     * Test import with invalid data.
     *
     * @return void
     */
    public function testInvalidImportFormat(): void
    {
        $xml = new XmlDoc();
        $this->expectExceptionMessage('Invalid parsed document format');
        $xml->import([]);
    }

    /**
     * Test export without data.
     *
     * @return void
     */
    public function testInvalidExport(): void
    {
        $xml = new XmlDoc();
        $this->expectExceptionMessage('No parsed document available');
        $xml->export();
    }

    /**
     * Data provider for testReading.
     *
     * @return \Iterator
     */
    public static function readingProvider(): \Iterator
    {
        yield 'no default ns' => [
            null,
            '{http://www.lido-schema.org}lido/{http://www.lido-schema.org}lidoRecID',
            '{http://www.lido-schema.org}type',
        ];

        yield 'no default ns, array query' => [
            null,
            ['http://www.lido-schema.org lido', 'http://www.lido-schema.org lidoRecID'],
            '{http://www.lido-schema.org}type',
        ];

        yield 'default ns' => [
            'http://www.lido-schema.org',
            '{http://www.lido-schema.org}lido/{http://www.lido-schema.org}lidoRecID',
            '{http://www.lido-schema.org}type',
        ];

        yield 'default ns, simple path' => [
            'http://www.lido-schema.org',
            'lido/lidoRecID',
            'type',
        ];
    }

    /**
     * Test reading.
     *
     * @param ?string      $defaultNs Default namespace
     * @param string|array $path      Path
     * @param string       $attr      Attribute name
     *
     * @return void
     */
    #[DataProvider('readingProvider')]
    public function testReading(?string $defaultNs, string|array $path, string $attr): void
    {
        $xmlStr = $this->getFixture('xml-with-multiple-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);

        $xml->setDefaultNamespace($defaultNs);
        $recId = $xml->all(path: $path);
        $this->assertIsArray($recId);
        $this->assertCount(1, $recId);
        $this->assertSame('record-id', $xml->value($recId[0]));
        $this->assertSame('local', $xml->attr($recId[0], $attr));
        $this->assertSame(
            [
                '{http://www.lido-schema.org}source' => 'Source',
                '{http://www.lido-schema.org}type' => 'local',
            ],
            $xml->attrs($recId[0])
        );
    }

    /**
     * Test reading values.
     *
     * @param ?string      $defaultNs Default namespace
     * @param string|array $path      Path
     * @param string       $attr      Attribute name
     *
     * @return void
     */
    #[DataProvider('readingProvider')]
    public function testReadingValues(?string $defaultNs, string|array $path, string $attr): void
    {
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);

        $xml->setDefaultNamespace($defaultNs);
        $recId = $xml->allValues(path: $path);
        $this->assertIsArray($recId);
        $this->assertCount(1, $recId);
        $this->assertSame('12345', $recId[0]);
    }

    /**
     * Data provider for testReadingAllValues.
     *
     * @return \Iterator
     */
    public static function readingAllValuesProvider(): \Iterator
    {
        yield 'ns' => ['xml-with-ns.xml'];
        yield 'no ns' => ['xml-without-ns.xml'];
    }

    /**
     * Test reading all values.
     *
     * @param string $fixture XML fixture
     *
     * @return void
     */
    #[DataProvider('readingAllValuesProvider')]
    public function testReadingAllValues(string $fixture): void
    {
        $xmlStr = $this->getFixture($fixture);
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $xml->setDefaultNamespace('http://www.lido-schema.org');
        $objectIdentificationWraps = $xml->all(path: 'lido/descriptiveMetadata/objectIdentificationWrap');
        $this->assertSame(
            [
                'Kitchen tool',
                'Scissors',
                'Sakset',
                'Keittiövälineet',
            ],
            $xml->allValues($objectIdentificationWraps[0], 'titleWrap/titleSet/appellationValue')
        );

        $objectIdentificationWrap = $xml->first(path: 'lido/descriptiveMetadata/objectIdentificationWrap');
        $this->assertSame(
            [
                'Kitchen tool',
                'Scissors',
                'Sakset',
                'Keittiövälineet',
            ],
            $xml->allValues($objectIdentificationWrap, 'titleWrap/titleSet/appellationValue')
        );
        $this->assertNull($xml->attr($objectIdentificationWrap, 'foo'));

        $this->assertSame([], $xml->allValues(path: 'foo'));
    }

    /**
     * Test mixed content.
     *
     * @return void
     */
    public function testMixedContent(): void
    {
        $xmlStr = $this->getFixture('mixed-content.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $xml->setDefaultNamespace('http://www.lido-schema.org', 'lido');
        $this->assertSame(
            'record-id',
            $xml->firstValue(path: 'lido/lidoRecID')
        );
        $this->assertSame(
            'root data',
            $xml->value($xml->root())
        );
        $this->assertSame(
            'lido',
            $xml->firstValue(path: '')
        );

        $this->assertSame(
            $this->getFixture('mixed-content-min.xml'),
            $xml->toXML(trim: true)
        );
    }

    /**
     * Test simple MARCXML roundtrip.
     *
     * @return void
     */
    public function testMarcXml(): void
    {
        $xmlStr = $this->getFixture('marcxml.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertXmlStringEqualsXmlString(
            $xmlStr,
            $xml->toXML(omitSinglePrefix: true)
        );
    }

    /**
     * Test MARCXML collection roundtrip.
     *
     * @return void
     */
    public function testMarcXmlCollection(): void
    {
        $xmlStr = $this->getFixture('marcxml-collection.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertXmlStringEqualsXmlString(
            $xmlStr,
            $xml->toXML()
        );
    }

    /**
     * Test empty elements.
     *
     * @return void
     */
    public function testEmptyElements(): void
    {
        $xmlStr = $this->getFixture('empty-elements.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $xml->setDefaultNamespace('http://www.lido-schema.org');
        $this->assertCount(
            3,
            $xml->all(path: 'lido/descriptiveMetadata/eventWrap/eventSet/event')
        );
    }

    /**
     * Data provider for testName.
     *
     * @return \Iterator
     */
    public static function nameProvider(): \Iterator
    {
        yield [false, false];
        yield [false, true];
        yield [true, false];
        yield [true, true];
    }

    /**
     * Test node name.
     *
     * @param bool $useDefaultNs Use default namespace?
     * @param bool $omitDefault  Omit default namespace from the name?
     *
     * @return void
     */
    #[DataProvider('nameProvider')]
    public function testName(bool $useDefaultNs, bool $omitDefault): void
    {
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $ns = 'http://www.lido-schema.org';
        if ($useDefaultNs) {
            $xml->setDefaultNamespace($ns);
        }
        $nodes = $xml->all($xml->first(path: $useDefaultNs ? 'lido' : "{{$ns}}lido"));
        $expectedPrefix = $useDefaultNs && $omitDefault
            ? ''
            : "{{$ns}}";
        $this->assertCount(2, $nodes);
        $this->assertSame("{$expectedPrefix}lidoRecID", $xml->name($nodes[0], $omitDefault));
        $this->assertSame("{$expectedPrefix}descriptiveMetadata", $xml->name($nodes[1], $omitDefault));
    }

    /**
     * Test invalid input XML.
     *
     * @return void
     */
    public function testInvalidXml(): void
    {
        $xmlStr = $this->getFixture('invalid.xml');
        $xml = new XmlDoc();
        $this->expectExceptionMessage("XML error 'Opening and ending tag mismatch: bar line 3 and baz' at 3:14");
        $xml->parse($xmlStr);
    }

    /**
     * Data provider for testInvalidQuery.
     *
     * @return \Iterator
     */
    public static function invalidQueryProvider(): \Iterator
    {
        yield 'no default ns' => [
            'lido/lidoRecID',
            "'lido' must use correct notation, or default namespace must be defined",
        ];
        yield 'extra {' => [
            '{{lido}lido/lidoRecId',
            'Unexpected repeated { in path: {{lido}lido',
        ];
        yield 'extra }' => [
            '{lido}lido}/lidoRecId',
            'Unexpected } in path: {lido}lido}',
        ];
    }

    /**
     * Test invalid query.
     *
     * @param string $path         Path
     * @param string $exceptionMsg Expected exception message
     *
     * @return void
     */
    #[DataProvider('invalidQueryProvider')]
    public function testInvalidQuery(string $path, string $exceptionMsg): void
    {
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->expectExceptionMessage($exceptionMsg);
        $xml->all(path: $path);
    }

    /**
     * Test uninitialized parser.
     *
     * @return void
     */
    public function testUninitializedParser(): void
    {
        $xml = new XmlDoc();
        $this->expectExceptionMessage('No parsed document available');
        $xml->all(path: '{lido}lido');
    }

    /**
     * Test uninitialized parser.
     *
     * @return void
     */
    public function testUninitializedParser2(): void
    {
        $xml = new XmlDoc();
        $this->expectExceptionMessage('No parsed document available');
        $xml->toXML();
    }

    /**
     * Test invalid XML.
     *
     * @return void
     */
    public function testMultipleNamespaces(): void
    {
        $xmlStr = $this->getFixture('xml-with-multiple-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);

        $this->assertSame(
            'FOO',
            $xml->firstValue(path: '{http://www.lido-schema.org}lido/{http://localhost}lidoRecID')
        );
        $this->assertSame(
            'record-id',
            $xml->firstValue(path: '{http://www.lido-schema.org}lido/{http://www.lido-schema.org}lidoRecID')
        );
    }

    /**
     * Test filter.
     *
     * @return void
     */
    public function testFilter(): void
    {
        $xml = new XmlDoc();
        $xml->parse($this->getFixture('xml-with-ns.xml'));
        $ns = 'http://www.lido-schema.org';
        $filterPath = "{{$ns}}lido/{{$ns}}descriptiveMetadata/{{$ns}}objectIdentificationWrap/{{$ns}}titleWrap"
            . "/{{$ns}}titleSet";
        $xml->filter(fn ($node, $path) => $path === $filterPath);
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-ns-filtered.xml'),
            $xml->toXML()
        );

        $xml->parse($this->getFixture('xml-with-ns.xml'));
        $xml->filter(fn ($node, $path) => $xml->name($node) === "{{$ns}}titleSet");
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-ns-filtered.xml'),
            $xml->toXML()
        );
    }

    /**
     * Test modify.
     *
     * @return void
     */
    public function testModify(): void
    {
        $xml = new XmlDoc();
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $expected = str_replace(
            [
                'xmlns:lido="http://www.lido-schema.org"',
                '<lido:term>Kirja</lido:term>',
                '<lido:appellationValue lido:pref="preferred">Kitchen tool</lido:appellationValue>',
                '<lido:appellationValue lido:pref="alternative">Scissors</lido:appellationValue>',
                '<lido:appellationValue lido:pref="alternative">Sakset</lido:appellationValue>',
                '<lido:appellationValue lido:pref="preferred">Keittiövälineet</lido:appellationValue>',
            ],
            [
                'xmlns:lido="http://www.lido-schema.org" xmlns:test="http://localhost/test"',
                '<test:testType attr="primary">value</test:testType>',
                '<lido:appellationValue xml:lang="foo">Modified Kitchen tool</lido:appellationValue>',
                '<lido:appellationValue xml:lang="bar">First New Title</lido:appellationValue>'
                . '<lido:appellationValue xml:lang="bar">Second New Title</lido:appellationValue>',
                '<lido:appellationValue xml:lang="foo">Modified Sakset</lido:appellationValue>',
                '',
            ],
            $xmlStr
        );

        $xml->parse($xmlStr);
        $ns = 'http://www.lido-schema.org';
        $titleSetPath = "{{$ns}}lido/{{$ns}}descriptiveMetadata/{{$ns}}objectIdentificationWrap/{{$ns}}titleWrap"
            . "/{{$ns}}titleSet";
        $titlePath = $titleSetPath . "/{{$ns}}appellationValue";
        $xml->modify(
            function (&$node, $path, $index) use ($xml, $titleSetPath, $titlePath, $ns) {
                if ($path === $titleSetPath && 0 === $index) {
                    $xml->addChild(
                        $node,
                        "{{$ns}}appellationValue",
                        'Second New Title',
                        ['{http://www.w3.org/XML/1998/namespace}lang' => 'bar']
                    );
                    $xml->addChild(
                        $node,
                        "{{$ns}}appellationValue",
                        'First New Title',
                        ['{http://www.w3.org/XML/1998/namespace}lang' => 'bar'],
                        2
                    );
                }
                if ($xml->localName($node) === 'objectWorkType') {
                    $xml->removeChildren($node);
                    $xml->addNamespacePrefix('http://localhost/test', 'test');
                    $xml->addChild($node, '{http://localhost/test}testType', 'value', ['attr' => 'primary']);
                }

                if ($path === $titlePath) {
                    if ($index === 1) {
                        return false;
                    }
                    if (!str_contains($xml->value($node), 'New Title')) {
                        $xml->setValue($node, 'Modified ' . $xml->value($node))
                            ->setAttr($node, "{{$ns}}pref", null)
                            ->setAttr($node, '{http://www.w3.org/XML/1998/namespace}lang', 'foo');
                    }
                }
            }
        );
        $this->assertXmlStringEqualsXmlString(
            $expected,
            $xml->toXML()
        );
    }

    /**
     * Test replaceChildren.
     *
     * @return void
     */
    public function testReplaceChildren(): void
    {
        $replacement = <<<EOT
            <doc xmlns:l="http://www.lido-schema.org" xmlns:lido="http://localhost/customlido"
              xmlns:custom="http://localhost/custom">
              <l:appellationValue lido:pref="preferred" custom:foo="bar">Replacement tool</l:appellationValue>
            </doc>
            EOT;

        $xmlReplacement = new XmlDoc();
        $xmlReplacement->parse($replacement);
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $expected = str_replace(
            [
                'xmlns:lido="http://www.lido-schema.org"',
                '<lido:appellationValue lido:pref="preferred">Kitchen tool</lido:appellationValue>',
                '<lido:appellationValue lido:pref="alternative">Scissors</lido:appellationValue>',
            ],
            [
                'xmlns:custom="http://localhost/custom" xmlns:lido="http://www.lido-schema.org"'
                . ' xmlns:lido2="http://localhost/customlido"',
                '<lido:appellationValue custom:foo="bar" lido2:pref="preferred">'
                . 'Replacement tool</lido:appellationValue>',
                '',
            ],
            $xmlStr
        );

        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $ns = 'http://www.lido-schema.org';
        $titleSetPath = "{{$ns}}lido/{{$ns}}descriptiveMetadata/{{$ns}}objectIdentificationWrap/{{$ns}}titleWrap"
            . "/{{$ns}}titleSet";
        $xml->modify(
            function (&$node, $path, $index) use ($xml, $titleSetPath, $xmlReplacement): void {
                if ($path === $titleSetPath && 0 === $index) {
                    $xml->replaceChildren($node, $xmlReplacement);
                }
            }
        );
        $this->assertXmlStringEqualsXmlString(
            $expected,
            $xml->toXML()
        );
    }

    /**
     * Test localName method.
     *
     * @return void
     */
    public function testLocalName(): void
    {
        $xmlStr = $this->getFixture('xml-with-ns.xml');
        $xml = new XmlDoc();
        $xml->parse($xmlStr);
        $this->assertSame(
            'lidoWrap',
            $xml->localName($xml->root())
        );
        $this->assertSame(
            'lidoWrap',
            $xml->localName('{foo}lidoWrap')
        );
    }

    /**
     * Test notation parsing.
     *
     * @return void
     */
    public function testNotationParsing(): void
    {
        $this->assertSame(
            ['foo', 'name'],
            Notation::parse('{foo}name')
        );
        $this->assertSame(
            ['foo', 'name'],
            Notation::parse('foo name')
        );
        $this->expectExceptionMessage("'foo' is invalid");
        Notation::parse('foo');
    }

    /**
     * Test invalid notation.
     *
     * @return void
     */
    public function testInvalidNotation(): void
    {
        $this->expectExceptionMessage("'foo' is invalid");
        Notation::parse('foo');
    }

    /**
     * Test another invalid notation.
     *
     * @return void
     */
    public function testInvalidNotation2(): void
    {
        $this->expectExceptionMessage("' foo ' is invalid");
        Notation::parse(' foo ');
    }

    /**
     * Test missing prefix definition for an element.
     *
     * @return void
     */
    public function testMissingElementPrefix(): void
    {
        $xml = new XmlDoc();
        $xml->parse($this->getFixture('xml-with-ns.xml'));
        $xml->modify(
            function (&$node, $path) use ($xml): void {
                if ($path === '{http://www.lido-schema.org}lido') {
                    $xml->setName($node, '{http://foo}lido');
                }
            }
        );
        $this->assertXmlStringEqualsXmlString(
            $this->getFixture('xml-with-ns1-ns.xml'),
            $xml->toXML()
        );
    }

    /**
     * Get a fixture.
     *
     * @param string $filename Filename
     *
     * @return string
     */
    protected function getFixture(string $filename): string
    {
        return file_get_contents(__DIR__ . '../../fixtures/' . $filename);
    }
}
