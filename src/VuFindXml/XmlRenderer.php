<?php

/**
 * XML Renderer.
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
 * @package  VuFindXml
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */

declare(strict_types=1);

namespace VuFindXml;

use RuntimeException;
use XMLWriter;

use function count;

/**
 * XML Renderer.
 *
 * This is a simple XML renderer.
 *
 * @category VuFindXml
 * @package  VuFindXml
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */
class XmlRenderer
{
    /**
     * Trim leading and trailing whitespace from text nodes?
     *
     * @var bool
     */
    protected bool $trim;

    /**
     * Omit namespace prefixes?
     *
     * @var bool
     */
    protected bool $omitNamespacePrefixes;

    /**
     * XML Writer.
     *
     * @var XMLWriter
     */
    protected XMLWriter $writer;

    /**
     * Constructor.
     *
     * @param array   $parsed                 Array of parsed data
     * @param ?string $defaultNamespace       Default namespace for elements missing a namespace, or null for none
     * @param ?string $defaultNamespacePrefix Default namespace prefix for default namespace, or null for none
     */
    public function __construct(
        protected array $parsed,
        protected ?string $defaultNamespace,
        protected ?string $defaultNamespacePrefix
    ) {
    }

    /**
     * Render the parsed array as an XML string.
     *
     * @param int    $indent           Indent (pretty-print) by $indent spaces
     * @param bool   $trim             Trim leading and trailing whitespace from text nodes?
     * @param ?array $node             Node to serialize (omit to serialize the full record)
     * @param bool   $omitSinglePrefix Omit namespace prefix if there's only a single namespace?
     *
     * @return string
     */
    public function render(
        int $indent = 0,
        bool $trim = false,
        ?array $node = null,
        bool $omitSinglePrefix = false
    ): string {
        $this->trim = $trim;
        // First go through all nodes and generate namespace prefixes as needed:
        $this->checkNode($node);
        $namespaces = $this->parsed['namespaces'];
        unset($namespaces['xsi']);
        $this->omitNamespacePrefixes = $omitSinglePrefix && count($namespaces) <= 1;
        $this->writer = new XMLWriter();
        $this->writer->openMemory();
        $this->writer->setIndent((bool)$indent);
        $this->writer->setIndentString(str_repeat(' ', $indent));
        $this->writer->startDocument();
        $this->nodeToXML($node, root: true);
        $this->writer->endDocument();
        return $this->writer->flush();
    }

    /**
     * Check node's namespace prefixes and add missing ones.
     *
     * @param ?array $node Node to write, or null to start from root
     *
     * @return void
     */
    protected function checkNode(?array $node = null): void
    {
        $current = $node ?? $this->parsed['data'];
        [$elementNs] = Notation::parse($current['name']);
        $elementNs = $elementNs ?: $this->defaultNamespace;
        if ($elementNs) {
            $this->getNamespacePrefix($elementNs);
        }
        // Sub-nodes:
        foreach ($current['sub'] as $subNode) {
            $this->checkNode($subNode);
        }
    }

    /**
     * Write a node to XMLWriter.
     *
     * @param ?array $node Node to write, or null to start from root
     * @param bool   $root Is this the root node?
     *
     * @return void
     */
    protected function nodeToXML(?array $node = null, bool $root = false): void
    {
        $current = $node ?? $this->parsed['data'];
        [$elementNs, $localName] = Notation::parse($current['name']);
        $elementNs = $elementNs ?: $this->defaultNamespace;
        $elementNsPrefix = null;
        if ($elementNs && !$this->omitNamespacePrefixes) {
            $elementNsPrefix = $this->getNamespacePrefix($elementNs);
            // Output namespace declaration only for root element (and not for xml namespace):
            $this->writer->startElementNs(
                $elementNsPrefix,
                $localName,
                $root && 'xml' !== $elementNsPrefix ? $elementNs : null
            );
        } else {
            $this->writer->startElement($localName);
        }
        // Write namespaces for root node:
        if ($root && $this->parsed['namespaces']) {
            $addNamespaces = $this->parsed['namespaces'];
            if ($this->defaultNamespace && $this->defaultNamespacePrefix) {
                $addNamespaces[$this->defaultNamespacePrefix] = $this->defaultNamespace;
            }
            unset($addNamespaces['xml']);
            unset($addNamespaces['xsi']);
            // Exclude root element's namespace and any other attribute namespaces as they're automatically added by
            // XMLWriter:
            if ($elementNsPrefix) {
                unset($addNamespaces[$elementNsPrefix]);
            }
            foreach ($current['attrs'] as $attr => $value) {
                if ($parsed = Notation::tryParse($attr)) {
                    [$ns] = $parsed;
                    $ns ??= $this->defaultNamespace;
                    if (false !== ($nsKey = array_search($ns, $addNamespaces))) {
                        unset($addNamespaces[$nsKey]);
                    }
                }
            }

            foreach ($addNamespaces as $prefix => $ns) {
                $this->writer->startAttribute($this->omitNamespacePrefixes ? 'xmlns' : "xmlns:$prefix");
                $this->writer->text($ns);
                $this->writer->endAttribute();
            }
        }
        // Write attributes:
        foreach ($current['attrs'] as $attr => $value) {
            if ($parsed = Notation::tryParse($attr)) {
                [$ns, $localName] = $parsed;
                $ns ??= $this->defaultNamespace;
            } else {
                $ns = $attr === 'schemaLocation'
                    ? 'http://www.w3.org/2001/XMLSchema-instance'
                    : $this->defaultNamespace;
                $localName = $attr;
            }
            if ($ns && !$this->omitNamespacePrefixes) {
                if (null === ($prefix = $this->getNamespacePrefix($ns))) {
                    throw new RuntimeException("No prefix found for namespace $ns");
                }
                $this->writer->startAttributeNs($prefix, $localName, $root ? $ns : null);
            } else {
                $this->writer->startAttribute($localName);
            }
            $this->writer->text($value);
            $this->writer->endAttribute();
        }
        $val = $this->trim ? trim($current['val']) : $current['val'];
        if ('' !== $val) {
            $this->writer->text($val);
        }
        // Sub-nodes:
        foreach ($current['sub'] as $subNode) {
            $this->nodeToXML($subNode);
        }
        $this->writer->endElement();
    }

    /**
     * Get namespace prefix for a namespace.
     *
     * @param string $ns Namespace
     *
     * @return string
     */
    protected function getNamespacePrefix(string $ns): string
    {
        if ($ns === $this->defaultNamespace && $this->defaultNamespacePrefix) {
            return $this->defaultNamespacePrefix;
        }
        if (false !== ($prefix = array_search($ns, $this->parsed['namespaces']))) {
            return $prefix;
        }
        // No existing prefix, add a new one:
        for ($i = 1; $i < 1000; $i++) {
            $prefix = 'ns' . (string)$i;
            if (!isset($this->parsed['namespaces'][$prefix])) {
                $this->parsed['namespaces'][$prefix] = $ns;
                return $prefix;
            }
        }
        throw new RuntimeException("Cannot find a free prefix slot for namespace $ns");
    }
}
