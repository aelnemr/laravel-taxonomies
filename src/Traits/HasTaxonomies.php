<?php

namespace Hamedov\Taxonomies\Traits;

use Hamedov\Taxonomies\Models\Taxonomy;
use Hamedov\Taxonomies\Models\Taxable;

/**
 * HasTaxonomies trait
 */
trait HasTaxonomies
{
	/**
	 * Get taxonomies of specific model by type
	 * @param  string|null $type
	 * @return [type]       [description]
	 */
	public function taxonomies($type = null)
	{
		if ($type !== null)
		{
			return $this->morphToMany(config('taxonomies.taxonomies_model', Taxonomy::class), 'taxable')
				->where('taxonomies.type', $type);
		}
		else
		{
			return $this->morphToMany(config('taxonomies.taxonomies_model', Taxonomy::class), 'taxable');
		}
	}

	/**
	 * Get all related entries in pivot table
	 * @return [type] [description]
	 */
	public function taxes($type = null)
	{
		if ($type === null)
		{
			return $this->morphMany(Taxable::class, 'taxable');
		}
		else
		{
			return $this->morphMany(Taxable::class, 'taxable')->whereHas('taxonomies', function($query) use ($type) {
				$query->where('taxonomies.type', $type);
			});
		}
	}

	public function addTaxonomy($taxonomy_id)
	{
		if ( ! $this->taxonomy_exists($taxonomy_id))
		{
			return false;
		}

		return $this->createTaxonomy($taxonomy_id);
	}

	public function createTaxonomy($taxonomy_id)
	{
		return $this->taxes()->firstOrCreate([
			'taxonomy_id' => $taxonomy_id,
			'taxable_id' => $this->getKey(),
			'taxable_type' => $this->getMorphClass(),
		]);
	}

	public function addTaxonomies($ids = [])
	{
		$actual_ids = $this->getModel()->whereIn('id', $ids)->pluck('id');
		if ($actual_ids->count() === 0)
		{
			return false;
		}

		$actual_ids->transform(function($id) {
			return $this->createTaxonomy($id);
		});

		return $actual_ids;
	}

	public function setTaxonomies($taxonomies = [], $type = null)
	{
		$this->removeTaxonomies($type);
		$this->addTaxonomies($taxonomies);
	}

	public function removeTaxonomy($taxonomy_id)
	{
		return $this->taxes()->where('taxables.taxonomy_id', $taxonomy_id)->delete();
	}

	public function removeTaxonomies($type = null, $ids = [])
	{
		$this->taxes($type)->when(count($ids) > 0, function($query) use ($ids) {
			$query->whereIn('taxables.taxonomy_id', $ids);
		})->delete();
	}

	public function removeAllTaxonomies()
	{
		$this->removeTaxonomies();
	}

	public function taxonomy_exists($id)
	{
		return (int) $this->getModel()->where('id', $id)->count() > 0;
	}

	public function scopeHasTaxonomy($query, $taxonomy_id)
	{
		return $query->whereHas('taxes', function($q) use ($taxonomy_id) {
			$q->where('taxables.taxonomy_id', $taxonomy_id);
		});
	}

	public function scopeHasAnyTaxonomy($query, $taxonomy_ids = [])
	{
		return $query->whereHas('taxes', function($q) use ($taxonomy_ids) {
			$q->whereIn('taxables.taxonomy_id', $taxonomy_ids);
		});
	}

	public function getModel()
	{
		return new config('taxonomies.taxonomies_model', Taxonomy::class);
	}
}
