<?php

namespace ErisRayanesh\LaravelFilter;

use Illuminate\Support\Collection;

class Sortable
{

	CONST ASC = "asc";
	CONST DESC = "desc";

	public static $default = "id asc";

	/**
	 * @var Collection
	 */
	protected $sortables;

	protected $actives;

	public function __construct($sortables, $sortBy)
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
	public function setSortables($sortables)
	{
		$this->sortables = collect($sortables);

		return $this;
	}

	/**
	 * @return Collection
	 */
	public function getSortedBy()
	{
		return $this->actives;
	}

	/**
	 * @param array $sortBy
	 * @return $this
	 */
	public function sort($sortBy)
	{
		$this->actives = collect($sortBy);
		$this->actives->reject(function($value, $key){
			$value = strtolower(trim($value));
			return !$this->getSortables()->has($key) || !self::isDirection($value);
		});
		return $this;
	}

	public function toString()
	{
		$sort = [];
		foreach ($this->getSortedBy() as $field => $dir){
			$sort[] = "$field $dir";
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
		$result = [];

		foreach ($this->getSortedBy() as $key => $item){
			$result[$key] = [
				'sorted' => true,
				'dir' => $item,
				'title' => $this->getSortables()->get($key),
			];
		}

		$this->getSortables()->except($this->getSortedBy()->keys()->toArray())->each(function($value, $key) use (&$result){
			$result[$key] = [
				'sorted' => false,
				'dir' => 'asc',
				'title' => $value,
			];
		});

		return $result;
	}

	public static function isDirection($value)
	{
		return $value === self::ASC || $value === self::DESC;
	}
}