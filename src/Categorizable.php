<?php
namespace UniSharp\Categorizable;

use Illuminate\Database\Eloquent\Builder;
use UniSharp\Categorizable\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait Categorizable
{
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizable');
    }

    public function categorize(...$categories): self
    {
        $result = $this->categories()->attach($this->differenceBetweenExistences($this->normalize($categories)));
        return $this->load('categories');
    }

    public function uncategorize(...$categories): self
    {
        $this->categories()->detach($this->normalize($categories));
        return $this->load('categories');
    }

    public function decategorize(): self
    {
        $this->categories()->sync([]);
        return $this->load('categories');
    }

    public function recategorize(...$categories): self
    {
        $this->decategorize()->categorize(...$categories);
        return $this->load('categories');
    }

    public function scopeHasCategories(Builder $builder, ...$categories): Builder
    {
        return $builder->whereHas('categories', function (Builder $query) use ($categories) {
            $query->whereIn('category_id', Category::descendantsAndSelf($this->normalize($categories))->pluck('id'));
        });
    }

    public function scopeHasStrictCategories(Builder $builder, ...$categories): Builder
    {
        return $builder->whereHas('categories', function (Builder $query) use ($categories) {
            $query->whereIn('category_id', $this->normalize($categories));
        });
    }

    /*
     * Convert everything to category ids
     */
    public function normalize(array $categories): array
    {
        $ids = collect($categories)->map(function ($categories) {
            switch (true) {
                case is_integer($categories) || is_numeric($categories):
                    return $categories;
                    break;
                case is_string($categories):
                    return Category::firstOrCreate(['name' => $categories])->id;
                    break;
                case is_array($categories):
                    return $this->normalize($categories);
                    break;
            }
        })->flatten()->toArray();

        // reject ids not exist in database
        return Category::whereIn('id', $ids)->pluck('id')->toArray();
    }

    protected function differenceBetweenExistences(array $ids): array
    {
        return array_diff($ids, $this->categories->pluck('id')->toArray());
    }
}
