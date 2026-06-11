<?php

namespace Tsrgtm\FilamentMediaLibrary\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tsrgtm\FilamentMediaLibrary\Filament\Resources\MediaResource;

class MediaLibraryPlugin implements Plugin
{
    protected string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected ?string $navigationLabel = null;
    protected ?string $navigationGroup = null;
    protected ?int $navigationSort = null;
    protected ?string $slug = null;

    protected bool $showSettings = true;

    public function getId(): string
    {
        return 'media-library';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MediaResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // 
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function navigationIcon(string | \BackedEnum | null $icon): static
    {
        $this->navigationIcon = $icon;
        return $this;
    }

    public function icon(string | \BackedEnum | null $icon): static
    {
        return $this->navigationIcon($icon);
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;
        return $this;
    }

    public function label(string $label): static
    {
        return $this->navigationLabel($label);
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;
        return $this;
    }

    public function slug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function showSettings(bool $show = true): static
    {
        $this->showSettings = $show;
        return $this;
    }

    public function getNavigationIcon(): string | \BackedEnum | null
    {
        return $this->navigationIcon;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function shouldShowSettings(): bool
    {
        return $this->showSettings;
    }
}
