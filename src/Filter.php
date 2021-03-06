<?php

namespace ErisRayanesh\LaravelFilter;

use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class Filter extends Collection
{

	protected $filterables = [];

	public function __construct(array $items = [], $checkEmpty = true)
	{
		parent::__construct([]);
		$this->setFilterables($items);
		$this->read($checkEmpty);
	}

	/**
	 * @return array
	 */
	public function getFilterables()
	{
		return $this->filterables;
	}

	/**
	 * @param array $filterables
	 * @return $this
	 */
	public function setFilterables($filterables)
	{
		$this->filterables = $filterables;
		return $this;
	}

	public function read ($checkEmpty = true)
	{

		foreach ($this->filterables as $key => $filterable) {

			$item = $key;
			$constraints = $filterable;

			if (is_int($key)){
				$item = $filterable;
				$constraints = [];
			}

			if (is_string($constraints)){
				$constraints = $this->parseConstraintsString($constraints);
			}

			$value = request()->get($item);

			$this->applyConstraints($value, $constraints);

			if ($value == null && !$this->isNullable($constraints)){
				continue;
			}

			$this->items[$item] = $value;
		}
	}

	protected function getFilterValue($key)
	{
		return request()->get($key);
	}

	protected function parseConstraintsString($constraints)
	{
//		if (strpos($constraints, "|") === false){
//			$items = ["type:" . $constraints];
//		} else {
		$items = Arr::where(explode("|", $constraints), function($item){
			return !empty($item);
		});
//		}

		$retVal = [];
		foreach ($items as $item) {
			if (strpos($item, ":") === false){
				$retVal[$item] = true;
				continue;
			}

			$parts = Arr::where(explode(":", $item), function($value){
				return !empty($value);
			});

			if (count($parts) != 2){
				continue;
			}

			$retVal[$parts[0]] = $parts[1];
		}

		return $retVal;
	}

	protected function isNullable($constraints)
	{
		return Arr::get($constraints, 'nullable', false);
	}

	protected function isArray($constraints)
	{
		return Arr::get($constraints, 'array', false);
	}

	protected function applyConstraints(&$value, $constraints)
	{
		if (is_callable($constraints)){
			$value = call_user_func($constraints, $value);
			return;
		}

		if (is_array($constraints)){
			foreach ($constraints as $key => $constraint) {
				$method = "apply" . title_case($key) . "Constraint";
				if (!method_exists($this, $method)){
					continue;
				}
				$this->$method($value, $constraint, $constraints);
			}
		}
	}

	protected function applyTypeConstraint(&$value, $constraint, $constraints)
	{
		if (is_callable($constraint)){
			$value = call_user_func($constraint, $value);
			return;
		}

		if (is_string($constraint)){
			$constraint = $this->parseTypeConstraintString($constraint);
		}

		$type = $constraint[0];

		if (in_array(strtolower($type), ['int', 'float', 'double', 'bool', 'boolean'])){
			return $this->applyScalarDataType($value, $type);
		}

		$value = $this->fetchFromModel($type, $value, $constraint, $constraints);
	}

	protected function applyAliasConstraint(&$value, $constraint, $constraints)
	{
		if (is_array($constraint) && count($constraint) < 2) {
			throw new \InvalidArgumentException('alias constraint must contain at least 2 parameters');
		}

		if (!is_array($constraint)){
			$constraint = $this->parseAliasConstraintString($constraint);
		}

		$key = array_shift($constraint);
		$modifier  = array_shift($constraint);

		if (is_callable($modifier)){
			$this->items[$key] = call_user_func($modifier, $value);
			return;
		}

		if ($value == null && !$this->isNullable($constraints)){
			return;
		}

		$this->items[$key] = $this->fetchFromModel($modifier, $value, $constraint, $constraints);
	}

	protected function parseTypeConstraintString($constraint)
	{
		if (strpos($constraint, ",") === false){
			return [$constraint];
		}

		return Arr::where(explode(",", $constraint), function($item){
			return !empty($item);
		});
	}

	protected function parseAliasConstraintString($constraint)
	{
		if (strpos($constraint, ",") === false){
			throw new \InvalidArgumentException('alias constraint must contain at least 2 parameters');
		}

		$retVal= Arr::where(explode(",", $constraint), function($item){
			return !empty($item);
		});

		if (count($retVal) < 2){
			throw new \InvalidArgumentException('alias constraint must contain at least 2 parameters');
		}

		return $retVal;
	}

	protected function applyScalarDataType($value, $constraint)
	{
		switch ($constraint) {
			case "int":
				$value = intval($value);
				break;
			case "float":
				$value = floatval($value);
				break;
			case "double":
				$value = doubleval($value);
				break;
			case "bool":
			case "boolean":
				$value = boolval($value);
				break;
		}

		return $value;
	}

	protected function fetchFromModel($model, $value, $constraint, array $constraints)
	{

		if (empty($value)){
			return $value;
		}

		$field = Arr::get($constraint, 1, 'id');
		$multiple = in_array("multiple", $constraint);

		if ($this->isArray($constraints) && is_array($value)){
			$retVal = $model::whereIn($field, $value)->get();
		} else {
			$method = $multiple? "find" : "findFirst";
			if ($multiple)
				$retVal = $model::where($field, $value)->get();
			else
				$retVal = $model::where($field, $value)->firstOrFail();
		}

		return $retVal;
	}


}
