<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Service;

use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Turns a tt_content row into an Inertia-friendly array:
 *
 *     ['CType' => 'text', 'data' => [...], 'html' => '<div>…</div>']
 *
 * `data` holds the record resolved through the core Record API (relations,
 * files), limited to $allowProperties if given. `html` is the element as
 * rendered by TypoScript (fluid_styled_content), the fallback for element
 * types the frontend has no component for. Skip it with $renderHtml = false
 * for types that have a component; rendering it costs time.
 */
class InertiaContentElementRenderer
{
    public function __construct(
        private readonly RecordFactory $recordFactory,
    ) {}

    /**
     * @param array<string, mixed> $record A tt_content row
     * @param list<string> $allowProperties Field names (as in the database) to include; empty means all
     * @return array{CType: string, data: array<array-key, mixed>, html: string|null}
     */
    public function renderRecord(
        array $record,
        ContentObjectRenderer $cObj,
        array $allowProperties = [],
        bool $useCamelCase = true,
        bool $renderHtml = true,
    ): array {
        $properties = $this->recordFactory
            ->createResolvedRecordFromDatabaseRow('tt_content', $record)
            ->toArray();

        if ($allowProperties !== []) {
            $properties = array_intersect_key($properties, array_flip($allowProperties));
        }

        $properties = $this->normalize($properties);

        if ($useCamelCase) {
            $properties = $this->convertKeysToCamelCase($properties);
        }

        $html = null;
        if ($renderHtml) {
            $html = $cObj->cObjGetSingle('RECORDS', [
                'tables' => 'tt_content',
                'source' => (string)$record['uid'],
                'dontCheckPid' => '1',
            ]);
        }

        return [
            'CType' => (string)$record['CType'],
            'data' => $properties,
            'html' => $html,
        ];
    }

    /**
     * Resolves records, collections and files into plain arrays that can be
     * JSON encoded.
     */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof FileReference || $value instanceof FileInterface) {
            return $this->normalizeFile($value);
        }

        if ($value instanceof RecordInterface) {
            return $this->normalize($value->toArray());
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string)$value : null;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFile(FileInterface $file): array
    {
        $properties = $file instanceof FileReference ? $file->getProperties() : [];

        return [
            'uid' => $file instanceof FileReference ? $file->getUid() : (int)$file->getProperty('uid'),
            'publicUrl' => $file->getPublicUrl(),
            'name' => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'size' => $file->getSize(),
            'title' => $properties['title'] ?? null,
            'alternative' => $properties['alternative'] ?? null,
            'description' => $properties['description'] ?? null,
            'width' => isset($properties['width']) ? (int)$properties['width'] : null,
            'height' => isset($properties['height']) ? (int)$properties['height'] : null,
        ];
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    private function convertKeysToCamelCase(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = is_string($key)
                ? lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))
                : $key;
            $result[$newKey] = is_array($value) ? $this->convertKeysToCamelCase($value) : $value;
        }

        return $result;
    }
}
