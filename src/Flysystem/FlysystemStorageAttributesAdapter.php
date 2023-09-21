<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Flysystem;

use League\Flysystem\StorageAttributes;


class FlysystemStorageAttributesAdapter implements StorageAttributes
{

    public $path = '';
    public $file = '';

    public function __construct($file, $path){
        $this->file = $file;
        $this->path= $path;
    }

    public function path(): string {
        return $this->path . '/' . $this->file->name;
    }

    public function type(): string{
        return $this->file->file->type;
    }

    public function visibility(): ?string{
        return 'true';
    }

    public function lastModified(): ?int{
        // $this->file->fileSystemInfo->lastModifiedDateTime
        return 0;
    }

    public static function fromArray(array $attributes): StorageAttributes{
        return new FlysystemStorageAttributesAdapter('temp', 'temp');
    }

    public function isFile(): bool{
        return true;
    }

    public function isDir(): bool{
        return true;
    }

    public function withPath(string $path): StorageAttributes{
        return new FlysystemStorageAttributesAdapter('temp', 'temp');
    }

    public function extraMetadata(): array{
        return [];
    }

    function jsonSerialize(): mixed{
        return '{}';
    }

    	/**
	 * Whether an offset exists
	 * Whether or not an offset exists.
	 *
	 * @param mixed $offset An offset to check for.
	 * @return bool Returns `true` on success or `false` on failure.
	 */
	function offsetExists($offset): bool{
        return true;
    }

	/**
	 * Offset to retrieve
	 * Returns the value at specified offset.
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return TValue Can return all value types.
	 */
	function offsetGet($offset): TValue{
        return '';
    }

	/**
	 * Assigns a value to the specified offset.
	 *
	 * @param TKey $offset The offset to assign the value to.
	 * @param TValue $value The value to set.
	 * @return void
	 */
	function offsetSet($offset, $value): void {}

	/**
	 * Unsets an offset.
	 *
	 * @param TKey $offset The offset to unset.
	 * @return void
	 */
	function offsetUnset($offset): void {}

}