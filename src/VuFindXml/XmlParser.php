<?php

/**
 * XML parser.
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

/**
 * XML parser.
 *
 * This is a light-weight XML parser inspired by sabre-xml. The deserialization format is slightly different, though.
 * And feature set is limited.
 *
 * @category VuFindXml
 * @package  VuFindXml
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/vufind-org/vufind-xml Git Repo
 */
class XmlParser extends \XMLReader
{
    /**
     * An associative array of namespaces found in the parsed document.
     *
     * @var array
     */
    protected array $namespaces = [];

    /**
     * Parse XML into an associative array.
     *
     * Returns an associative array with the following keys:
     *   v     - Format version
     *   data  - Array of elements with the following keys:
     *     name  - Element name
     *     val   - Values (text content)
     *     sub   - Child nodes
     *     attrs - Attributes
     *   namespaces - Array of namespaces
     *
     * @param string $xml   XML string
     * @param int    $flags Additional LibXML flags (LIBXML_NONET is always enabled)
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function parse(string $xml, int $flags = LIBXML_COMPACT): array
    {
        $this->namespaces = [
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        ];
        $saveInternalErrors = libxml_use_internal_errors(true);
        try {
            $this->XML($xml, flags: $flags | LIBXML_NONET);

            while (self::ELEMENT !== $this->nodeType) {
                $this->read();
            }
            $result = $this->processElement();
        } finally {
            libxml_use_internal_errors($saveInternalErrors);
        }

        return [
            'v' => 1,
            'data' => $result,
            'namespaces' => $this->namespaces,
        ];
    }

    /**
     * Validate an array as parsed record.
     *
     * Does a simple sanity check, no exhaustive analysis.
     *
     * @param array $parsed Parsed record
     *
     * @return bool
     */
    public function validate(array $parsed): bool
    {
        return isset($parsed['data']) && ($parsed['v'] ?? null) === 1;
    }

    /**
     * Move to next node in document.
     *
     * Throws an exception on failure.
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function read(): bool
    {
        if (!parent::read()) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            if ($errors !== []) {
                throw new \RuntimeException($this->errorsToString($errors));
            }
        }
        return true;
    }

    /**
     * Process the current XML element.
     *
     * @return array <string, mixed>
     */
    protected function processElement(): array
    {
        $result = [
            'name' => $this->getCurrentName(),
            'val' => '',
            'sub' => [],
            'attrs' => $this->getAttributes(),
        ];

        if (self::ELEMENT === $this->nodeType && $this->isEmptyElement) {
            return $result;
        }

        while (true) {
            $this->read();
            switch ($this->nodeType) {
                case self::ELEMENT:
                    $result['sub'][] = $this->processElement();
                    break;
                case self::TEXT:
                case self::CDATA:
                    $result['val'] .= $this->value;
                    break;
                case self::END_ELEMENT:
                case self::NONE:
                    return $result;
                default:
                    break;
            }
        }
    }

    /**
     * Get all attributes from current element.
     *
     * @return array<string, string>
     */
    protected function getAttributes(): array
    {
        if (!$this->hasAttributes) {
            return [];
        }

        $attributes = [];
        while ($this->moveToNextAttribute()) {
            if ($this->namespaceURI) {
                if ('http://www.w3.org/2000/xmlns/' === $this->namespaceURI) {
                    continue;
                }
                $attributes[$this->getCurrentName()] = $this->value;
            } else {
                $attributes[$this->localName] = $this->value;
            }
        }
        $this->moveToElement();

        return $attributes;
    }

    /**
     * Get current nodename in our notation.
     *
     * @return ?string
     */
    protected function getCurrentName(): ?string
    {
        if ($this->prefix) {
            $this->namespaces[$this->prefix] = $this->namespaceURI;
        }
        return $this->localName ? ('{' . $this->namespaceURI . '}' . $this->localName) : null;
    }

    /**
     * Convert LibXML errors to a string.
     *
     * @param array $errors LibXML errors
     *
     * @return string
     */
    protected function errorsToString(array $errors): string
    {
        $message = trim($errors[0]->message);
        return "XML error '$message' at {$errors[0]->line}:{$errors[0]->column}";
    }
}
