<?php

namespace Tsrgtm\FilamentMediaLibrary\Support;

class MediaCollection
{
    public string $name;
    public bool $single = false;
    public array $conversions = [];
    public ?string $fallback = null;

    public function __construct(string $name, array $options = [])
    {
        $this->name = $name;
        $this->single = $options['single'] ?? false;
        $this->conversions = $options['conversions'] ?? [];
        $this->fallback = $options['fallback'] ?? null;
    }

    /**
     * Create a collection instance from configuration array.
     */
    public static function make(string $name, array $options = []): self
    {
        return new static($name, $options);
    }
}
