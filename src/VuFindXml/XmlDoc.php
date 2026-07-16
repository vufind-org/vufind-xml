<?php

/**
 * XML Document.
 *
 * PHP version 8
 *
 * Copyright (C) 2025-2026 University of Helsinki, National library of Finland.
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

use InvalidArgumentException;
use RuntimeException;

use function in_array;
use function is_array;

/**
 * XML Document.
 *
 * @category VuFindXml
 * @package  VuFindXml
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */
class XmlDoc
{
    /**
     * Parsed XML.
     *
     * @var ?array
     */
    protected ?array $parsed = null;

    /**
     * Default namespace URI for path parts, elements and attributes without namespace.
     *
     * @var ?string
     */
    protected ?string $defaultNamespace = null;

    /**
     * Default namespace prefix for path parts, elements and attributes without namespace.
     *
     * @var ?string
     */
    protected ?string $defaultNamespacePrefix = null;

    /**
     * Parse an XML string.
     *
     * @param string $xml XML
     *
     * @return static
     */
    public function parse(string $xml): static
    {
        $this->parsed = (new XmlParser())->parse($xml);
        return $this;
    }

    /**
     * Serialize the document as XML.
     *
     * @param int    $indent           Indent (pretty-print) by $indent spaces
     * @param bool   $trim             Trim leading and trailing whitespace from text nodes?
     * @param ?array $node             Node to serialize (omit to serialize the full record)
     * @param bool   $omitSinglePrefix Omit namespace prefix if there's only a single namespace?
     *
     * @return string
     */
    public function toXML(
        int $indent = 0,
        bool $trim = false,
        ?array $node = null,
        bool $omitSinglePrefix = false
    ): string {
        if (null === $this->parsed) {
            throw new RuntimeException('No parsed document available');
        }

        return (new XmlRenderer($this->parsed, $this->defaultNamespace, $this->defaultNamespacePrefix))
            ->render($indent, $trim, $node, $omitSinglePrefix);
    }

    /**
     * Import a previously parsed document array.
     *
     * @param array $parsed Parsed record.
     *
     * @return void
     */
    public function import(array $parsed): void
    {
        if (!(new XmlParser())->validate($parsed)) {
            throw new RuntimeException('Invalid parsed document format');
        }
        $this->parsed = $parsed;
    }

    /**
     * Export a previously parsed document array or node.
     *
     * @param ?array $node Node to export (omit to export the full record)
     *
     * @return array
     */
    public function export(?array $node = null): array
    {
        if (null === $this->parsed) {
            throw new RuntimeException('No parsed document available');
        }
        if (null === $node) {
            return $this->parsed;
        }
        $result = $this->parsed;
        $result['data'] = $node;
        return $result;
    }

    /**
     * Set default namespace for path queries.
     *
     * @param ?string $namespace Namespace URI, or null for no default
     * @param ?string $prefix    Prefix for any default namespace (used when rendering XML)
     *
     * @return static
     */
    public function setDefaultNamespace(?string $namespace, ?string $prefix = null): static
    {
        $this->defaultNamespace = $namespace;
        $this->defaultNamespacePrefix = $prefix;
        return $this;
    }

    /**
     * Add a namespace prefix.
     *
     * @param string $namespace Namespace URI
     * @param string $prefix    Prefix to use
     *
     * @return static
     */
    public function addNamespacePrefix(string $namespace, string $prefix): static
    {
        if (null === $this->parsed) {
            throw new RuntimeException('No parsed document available');
        }
        $this->parsed['namespaces'][$prefix] = $namespace;
        return $this;
    }

    /**
     * Get root node.
     *
     * @return ?array Root node, or null if uninitialized
     */
    public function root(): ?array
    {
        return $this->parsed['data'] ?? null;
    }

    /**
     * Get all nodes by path starting from the given single node.
     *
     * @param ?array       $node Node to start from (optional)
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return array[]
     */
    public function all(?array $node = null, string|array $path = ''): array
    {
        return $this->allByPath($node, $path);
    }

    /**
     * Get first node by path.
     *
     * @param ?array       $node Node to start from (optional)
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return ?array
     */
    public function first(?array $node = null, string|array $path = ''): ?array
    {
        return $this->all($node, $path)[0] ?? null;
    }

    /**
     * Get all node values by path starting from the given single node.
     *
     * @param ?array       $node        Node to start from (optional)
     * @param string|array $path        Path (array or a slash-delimited string) with each node either in Clark
     * notation, or just node name with $this->defaultNamespace defined
     * @param bool         $trim        Trim results?
     * @param bool         $emptyValues Include empty values?
     *
     * @return string[]
     */
    public function allValues(
        ?array $node = null,
        string|array $path = '',
        bool $trim = true,
        bool $emptyValues = false
    ): array {
        $results = $this->getValues($this->all($node, $path));
        if (!$emptyValues) {
            $results = array_values(array_filter($results, fn ($s) => '' !== $s));
        }
        return $trim ? array_map('trim', $results) : $results;
    }

    /**
     * Get first node value as string by path.
     *
     * @param ?array       $node Node to start from (optional)
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     * @param bool         $trim Trim result?
     *
     * @return ?string
     */
    public function firstValue(?array $node = null, string|array $path = '', bool $trim = true): ?string
    {
        $first = $this->first($node, $path);
        $result = $first['val'] ?? null;
        return ($trim && null !== $result) ? trim($result) : $result;
    }

    /**
     * Get attribute from a node.
     *
     * @param ?array $node Node
     * @param string $attr Attribute either in Clark notation, or just name with $this->defaultNamespace defined
     * @param bool   $trim Trim result?
     *
     * @return ?string
     */
    public function attr(?array $node, string $attr, bool $trim = true): ?string
    {
        // Try to find the attribute first with namespace and fall back to search without namespace:
        $result = null;
        if ($parsed = Notation::tryParse($attr)) {
            $nsAttr = '{' . $parsed[0] . '}' . $parsed[1];
            $result = $node['attrs'][$nsAttr] ?? $node['attrs'][$parsed[1]] ?? null;
        } else {
            // Try with default namespace:
            if (null !== $this->defaultNamespace) {
                $nsAttr = '{' . $this->defaultNamespace . '}' . $attr;
                $result = $node['attrs'][$nsAttr] ?? null;
            }
            $result ??= $node['attrs'][$attr] ?? null;
        }
        return ($trim && null !== $result) ? trim($result) : $result;
    }

    /**
     * Get all attributes from a node.
     *
     * @param ?array $node Node
     * @param bool   $trim Trim results?
     *
     * @return array
     */
    public function attrs(?array $node, bool $trim = true): array
    {
        $result = $node['attrs'] ?? [];
        return $trim ? array_map('trim', $result) : $result;
    }

    /**
     * Set attribute value.
     *
     * Note: This method is typically used with modify(); it only updates the node but does not modify the document!
     *
     * @param array   $node  Node
     * @param string  $attr  Attribute name either in Clark notation, or just name
     * @param ?string $value Attribute value, or null to unset
     *
     * @return static
     */
    public function setAttr(array &$node, string $attr, ?string $value): static
    {
        if (null === $value) {
            unset($node['attrs'][$attr]);
        } else {
            $node['attrs'][$attr] = $value;
        }
        return $this;
    }

    /**
     * Get the name of a node.
     *
     * @param array $node          Node
     * @param bool  $omitDefaultNs Leave out namespace if it's the default
     *
     * @return string
     */
    public function name(array $node, bool $omitDefaultNs = false): string
    {
        if (
            $omitDefaultNs
            && $this->defaultNamespace
        ) {
            $parsed = Notation::parse($node['name']);
            if ($parsed[0] === $this->defaultNamespace) {
                return $parsed[1];
            }
        }
        return $node['name'];
    }

    /**
     * Get the local name of a node or qualified name.
     *
     * @param string|array $nodeOrName Node or a qualified name
     *
     * @return string
     */
    public function localName(string|array $nodeOrName): string
    {
        [, $localName] = Notation::parse(is_array($nodeOrName) ? $nodeOrName['name'] : $nodeOrName);
        return $localName;
    }

    /**
     * Set node name.
     *
     * Note: This method is typically used with modify(); it only updates the node but does not modify the document!
     *
     * @param array  $node Node
     * @param string $name Name
     *
     * @return static
     */
    public function setName(array &$node, string $name): static
    {
        $node['name'] = $name;
        return $this;
    }

    /**
     * Get the string value of a node.
     *
     * @param array $node Node
     * @param bool  $trim Trim result?
     *
     * @return string
     */
    public function value(array $node, bool $trim = true): string
    {
        return $trim ? trim($node['val']) : $node['val'];
    }

    /**
     * Set node value.
     *
     * Note: This method is typically used with modify(); it only updates the node but does not modify the document!
     *
     * @param array  $node  Node
     * @param string $value Value
     *
     * @return static
     */
    public function setValue(array &$node, string $value): static
    {
        $node['val'] = $value;
        return $this;
    }

    /**
     * Add a child node.
     *
     * Note: This method is typically used with modify(); it only updates the node but does not modify the document!
     * Make sure that the namespace is known (use addNamespacePrefix) if you need to serialize the XML.
     *
     * @param array  $node     Parent node
     * @param string $name     Node name
     * @param string $value    Node value
     * @param array  $attrs    Attributes
     * @param ?int   $position Position in child list (0 = first), or null to append
     *
     * @return static
     */
    public function addChild(
        array &$node,
        string $name,
        string $value,
        array $attrs = [],
        ?int $position = null
    ): static {
        $new = [
            'name' => $name,
            'val' => $value,
            'sub' => [],
            'attrs' => $attrs,
        ];
        if (null === $position) {
            $node['sub'][] = $new;
        } else {
            array_splice($node['sub'], $position, 0, [$new]);
        }
        return $this;
    }

    /**
     * Remove all child nodes.
     *
     * Note: This method is typically used with modify(); it only updates the node, but does not modify the document!
     *
     * @param array $node Parent node
     *
     * @return static
     */
    public function removeChildren(array &$node): static
    {
        $node['sub'] = [];
        return $this;
    }

    /**
     * Replace all child nodes with the nodes from another XmlDoc (exluding the root element).
     *
     * Note: This method is typically used with modify(); it only updates the node and this instance's namespace
     * prefixes, but does not modify the document!
     *
     * @param array  $node     Parent node
     * @param XmlDoc $otherDoc XmlDoc with the nodes to use
     *
     * @return static
     */
    public function replaceChildren(array &$node, XmlDoc $otherDoc): static
    {
        $exported = $otherDoc->export();
        // Ensure all namespaces have prefixes:
        foreach ($exported['namespaces'] as $prefix => $namespace) {
            if (in_array($namespace, $this->parsed['namespaces'])) {
                continue;
            }
            if (null !== ($existing = $this->parsed['namespaces'][$prefix] ?? null)) {
                if ($existing !== $namespace) {
                    // Collision, find a free prefix:
                    $newPrefix = null;
                    for ($i = 2; $i < 100; $i++) {
                        if (!isset($this->namespaces[$prefix . (string)$i])) {
                            $newPrefix = $prefix . (string)$i;
                            break;
                        }
                    }
                    if (null === $newPrefix) {
                        throw new RuntimeException("Cannot find a free namespace prefix for $namespace");
                    }
                    $this->parsed['namespaces'][$newPrefix] = $namespace;
                }
            } else {
                $this->parsed['namespaces'][$prefix] = $namespace;
            }
        }
        $node['sub'] = $exported['data']['sub'];

        return $this;
    }

    /**
     * Filter nodes.
     *
     * Calls the callback for each node and removes the node if the callback returns true.
     *
     * @param callable $callback Callback
     *
     * @return void
     */
    public function filter(callable $callback): void
    {
        $this->parsed['data']['sub'] = $this->filterRecursive($callback, [$this->parsed['data']], []);
    }

    /**
     * Modify nodes.
     *
     * Calls the callback for each node to allow it to be updated.
     *
     * @param callable $callback Callback
     *
     * @return void
     */
    public function modify(callable $callback): void
    {
        $this->parsed['data']['sub'] = $this->modifyRecursive($callback, [$this->parsed['data']], []);
    }

    /**
     * Filter nodes recursively.
     *
     * Calls the callback for each node and removes the node if the callback returns true.
     *
     * @param callable $callback  Callback
     * @param array    $nodeStack Parent node stack
     * @param array    $path      Current path
     *
     * @return array
     */
    protected function filterRecursive(callable $callback, array $nodeStack, array $path): array
    {
        $result = [];
        $node = end($nodeStack);
        foreach ($node['sub'] as $i => $subNode) {
            $subPath = [...$path, $subNode['name']];
            $subStack = [...$nodeStack, $subNode];
            if (!$callback($subNode, implode('/', $subPath), $i, $subStack)) {
                $subNode['sub'] = $this->filterRecursive($callback, $subStack, $subPath);
                $result[] = $subNode;
            }
        }
        return $result;
    }

    /**
     * Modify nodes recursively.
     *
     * Calls the callback for each node to allow it to be updated.
     *
     * @param callable $callback  Callback
     * @param array    $nodeStack Parent node stack
     * @param array    $path      Current path
     *
     * @return array
     */
    protected function modifyRecursive(callable $callback, array $nodeStack, array $path): array
    {
        $result = [];
        $node = end($nodeStack);
        foreach ($node['sub'] as $i => $subNode) {
            $subPath = [...$path, $subNode['name']];
            $subStack = [...$nodeStack, $subNode];
            if (false !== $callback($subNode, implode('/', $subPath), $i, $subStack)) {
                // Recreate subStack with any modifications:
                $subStack = [...$nodeStack, $subNode];
                $subNode['sub'] = $this->modifyRecursive($callback, $subStack, $subPath);
                $result[] = $subNode;
            }
        }
        return $result;
    }

    /**
     * Recursively traverse all branches by path and return any values found.
     *
     * @param ?array       $root Node to start from
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation
     * just node name with $this->defaultNamespace defined
     *
     * @return array
     */
    protected function allByPath(?array $root, string|array $path): array
    {
        $currentNodes = $root['sub'] ?? $this->root()['sub'] ?? null;
        if (null === $currentNodes) {
            throw new RuntimeException('No parsed document available');
        }
        if (!$path) {
            return $currentNodes;
        }
        $remainingPath = is_array($path) ? $path : $this->explodePath($path);
        $pathPart = array_shift($remainingPath);

        // Verify that the path part has namespace:
        $pathPart = Notation::ensureValid($pathPart, $this->defaultNamespace);

        // Try to find nodes first with namespace and fall back to search without namespace:
        foreach ([false, true] as $fallback) {
            if ($fallback) {
                $clark = Notation::parse($pathPart);
                $pathPart = '{}' . $clark[1];
            }
            $result = null;
            foreach ($currentNodes as $node) {
                if ($pathPart === $node['name']) {
                    if ($remainingPath !== []) {
                        if ($node['sub']) {
                            $result = [
                                ...($result ?? []),
                                ...$this->allByPath($node, $remainingPath),
                            ];
                        }
                    } else {
                        $result[] = $node;
                    }
                }
            }
            if (null !== $result) {
                return $result;
            }
        }

        return [];
    }

    /**
     * Get values from an array of nodes.
     *
     * @param array $nodes Nodes
     *
     * @return string[]
     */
    protected function getValues(array $nodes): array
    {
        return array_map(
            function ($node): string {
                return $node['val'];
            },
            $nodes
        );
    }

    /**
     * Explode a path string to an array.
     *
     * @param string $path Path
     *
     * @return array
     */
    protected function explodePath(string $path): array
    {
        if (!str_contains($path, '/')) {
            return [$path];
        }
        if (!str_contains($path, '{')) {
            return explode('/', $path);
        }
        $parts = [];
        $collected = '';
        $inNs = false;
        foreach (str_split($path) as $c) {
            switch ($c) {
                case '{':
                    if ($inNs) {
                        throw new InvalidArgumentException('Unexpected repeated { in path: ' . $path);
                    }
                    $inNs = true;
                    break;
                case '}':
                    if (!$inNs) {
                        throw new InvalidArgumentException('Unexpected } in path: ' . $path);
                    }
                    $inNs = false;
                    break;
                case '/':
                    if (!$inNs) {
                        $parts[] = $collected;
                        $collected = '';
                        continue 2;
                    }
                    break;
            }
            $collected .= $c;
        }
        if ('' !== $collected) {
            $parts[] = $collected;
        }
        return $parts;
    }
}
