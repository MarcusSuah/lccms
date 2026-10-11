<?php

namespace App\Enums\Geography;

use App\Enums\Concerns\HasOptions;
use App\Models\Geography\Block;
use App\Models\Geography\Clan;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Geography\District;
use App\Models\Geography\Town;
use Illuminate\Database\Eloquent\Model;

/**
 * The administrative levels of the confirmed hierarchy, ordered from the top.
 */
enum GeoLevel: string
{
    use HasOptions;

    case County = 'county';
    case District = 'district';
    case Clan = 'clan';
    case Town = 'town';
    case Community = 'community';
    case Block = 'block';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The foreign-key column used for this level on denormalised tables.
     */
    public function column(): string
    {
        return $this->value.'_id';
    }

    public function table(): string
    {
        return match ($this) {
            self::County => 'counties',
            self::District => 'districts',
            self::Clan => 'clans',
            self::Town => 'towns',
            self::Community => 'communities',
            self::Block => 'blocks',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::County => County::class,
            self::District => District::class,
            self::Clan => Clan::class,
            self::Town => Town::class,
            self::Community => Community::class,
            self::Block => Block::class,
        };
    }

    public function depth(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function parent(): ?self
    {
        return self::cases()[$this->depth() - 1] ?? null;
    }

    public function child(): ?self
    {
        return self::cases()[$this->depth() + 1] ?? null;
    }

    /**
     * The reference-code segment prefix used when generating child codes.
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::County => 'CO',
            self::District => 'D',
            self::Clan => 'CL',
            self::Town => 'T',
            self::Community => 'C',
            self::Block => 'B',
        };
    }

    /**
     * This level and every level above it, top first.
     *
     * @return list<self>
     */
    public function lineage(): array
    {
        return array_slice(self::cases(), 0, $this->depth() + 1);
    }

    public function isAbove(self $other): bool
    {
        return $this->depth() < $other->depth();
    }
}
