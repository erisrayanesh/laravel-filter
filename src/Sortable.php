<?php

namespace ErisRayanesh\LaravelFilter;

use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class Sortable
{

	CONST ASC = "asc";
	CONST DESC = "desc";

	public static $default = "id asc";

	/**
	 * @var Collection
	 */
	protected $sortables;

	/**
	 * Sortable constructor.
	 * @param $sortables
	 * @param $sortBy
	 */
	public function __construct(array $sortables, $sortBy = [])
	{
		$this->setSortables($sortables);
		$this->sort($sortBy);
	}

	/**
	 * @return Collection
	 */
	public function getSortables()
	{
		return $this->sortables;
	}

	/**
	 * @param mixed $sortables
	 * @return Sortable
	 */
	public function setSortables(array $sortables)
	{
		$items = [];
		foreach ($sortables as $key => $value) {
			$items[$key] = [
				'title' => $value,
				'dir' => self::ASC,
				'sorted' => false,
			];
		};
		$this->sortables = collect($items);
		return $this;
	}

	/**
	 * @param array $sortBy
	 * @return $this
	 */
	public function sort(array $sortBy)
	{
		//reject any invalid items in sortBy
		$sortBy = collect($sortBy)->reject(function($value, $key){
			return !$this->getSortables()->has($key) || !self::isDirection(strtolower(trim($value)));
		});

		// create new collection
		$items = collect([]);
		foreach ($sortBy as $key => $value) {
			$item = $this->getSortables()->get($key);
			$item["sorted"] = true;
			$item["dir"] = $value;
			$items->put($key, $item);
		}

		// merge rest of items to sortables
		$items = $items->merge($this->getSortables()->except($sortBy->keys()));

		$this->sortables = $items;

		return $this;
	}

	public function toString(array $config = [])
	{
		$items = $this->getSortables();

		if (isset($config['only'])) {
			$items = $this->getSortables()->only(Arr::get($config, 'only', []));
		} elseif (isset($config['except'])) {
			$items = $this->getSortables()->except(Arr::get($config, 'except', []));
		}

		$items = $items->where('sorted', true);

		$sort = [];
		foreach ($items as $field => $value){
			$sort[] = "$field {$value['dir']}";
		}
		$sort = implode(", ", $sort);
		if (empty($sort)){
			$sort = self::$default;
		}
		return $sort;
	}

	public function __toString()
	{
		return $this->toString();
	}

	public function result()
	{
		return $this->getSortables();
	}

	public function has($key)
	{
		return $this->getSortables()->has($key);
	}

	public function get($key)
	{
		return $this->getSortables()->get($key);
	}

	public function getDir($key)
	{
		if ($this->has($key)){
			return $this->get($key)['dir'];
		}
		return self::ASC;
	}

	public function getTitle($key)
	{
		if ($this->has($key)){
			return $this->get($key)['title'];
		}
		return null;
	}

	public function isSorted($key)
	{
		if ($this->has($key)){
			return $this->get($key)['sorted'];
		}
		return false;
	}


	public static function isDirection($value)
	{
		return $value === self::ASC || $value === self::DESC;
	}
}
