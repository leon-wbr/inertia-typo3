<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Service;

use TYPO3\CMS\Core\Collection\LazyRecordCollection;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class InertiaContentElementRenderer
{
  public function __construct(
    private readonly ContentObjectRenderer $cObj,
    private readonly RecordFactory $recordFactory
  ) {}

  public function renderRecord(array $record, ?array $allowProperties = [], bool $useCamelCase = true): array
  {
    $conf = [
      'tables' => 'tt_content',
      'source' => $record['uid'],
    ];

    $contentElement = $this->recordFactory->createResolvedRecordFromDatabaseRow('tt_content', $record);
    $contentElementArr = $contentElement->toArray();

    foreach ($contentElement->toArray() as $key => $value) {
      if ($value instanceof LazyRecordCollection || $value instanceof LazyFileReferenceCollection) {
        if ($contentElementArr[$key] instanceof LazyRecordCollection || !is_array($contentElementArr[$key])) {
          $contentElementArr[$key] = [];
        }

        foreach ($value->getIterator() as $subKey => $subValue) {
          $contentElementArr[$key][$subKey] = $subValue->toArray();
        }
      }
    }

    $html = $this->cObj->cObjGetSingle('RECORDS', $conf);
    $properties = empty($allowProperties) ? $contentElementArr : array_intersect_key($contentElementArr, array_flip($allowProperties));

    if ($useCamelCase) {
      $properties = $this->convertKeysToCamelCase($contentElementArr);
    }

    return [
      'CType' => $record['CType'],
      'data' => $properties,
      'html' => $html,
    ];
  }

  private function convertKeysToCamelCase(array $array): array
  {
    $result = [];
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        $result[$key] = $value;
        continue;
      }
      $camelKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
      $result[$camelKey] = is_array($value) ? $this->convertKeysToCamelCase($value) : $value;
    }
    return $result;
  }
}
