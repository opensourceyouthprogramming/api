<?php
/**
 * @author: KentProjects <developer@kentprojects.com>
 * @license: Copyright KentProjects
 * @link: http://kentprojects.com
 */
abstract class Intent
{
	const STATE_OPEN = "intent:state:open";
	const STATE_ACCEPTED = "intent:state:accepted";
	const STATE_REJECTED = "intent:state:rejected";

	/**
	 * @param int $id
	 * @return Intent
	 */
	public static function getById($id)
	{
		$model = Model_Intent::getById($id);
		if (empty($model))
		{
			return null;
		}

		/** @var Intent $class */
		$class = static::getHandlerClassName($model->getHandler());
		return new $class($model);
	}

	/**
	 * @param string $handler
	 * @return string
	 */
	public static function getHandlerClassName($handler)
	{
		$className = "Intent_";
		$className .= implode(
			"_", array_map(
				function ($h)
				{
					return ucfirst($h);
				},
				explode("_", static::formatHandler($handler))
			)
		);
		if (!class_exists($className))
		{
			trigger_error("Class not found: $className", E_USER_ERROR);
		}

		return $className;
	}

	/**
	 * @param Model_User $user
	 * @return array
	 */
	public static function getOpenByUser(Model_User $user)
	{
		$ids = Cache::get($user->getCacheName("intents"));
		if (empty($ids))
		{
			$ids = Database::prepare(
				"SELECT `intent_id` FROM `Intent` WHERE `state` = 'open' AND `user_id` = ?", "i"
			)->execute($user->getId())->singlevals();
			!empty($ids) && Cache::get($user->getCacheName("intents"), 2 * Cache::HOUR);
		}
		return array_filter(array_map(array(get_called_class(), "getById"), $ids));
	}

	/**
	 * @param string $handler
	 * @return string
	 */
	public static function formatHandler($handler)
	{
		return strtolower(
			str_replace("-", "_", $handler)
		);
	}

	/**
	 * @var Metadata
	 */
	protected $data;
	/**
	 * @var Model_Intent
	 */
	protected $model;

	public function __construct(Model_Intent $model)
	{
		$this->model = $model;

		if ($this->model->getHandler() !== $this->getHandlerName())
		{
			throw new InvalidArgumentException("This model's handler is different to this handler.");
		}

		$this->data = new Metadata(($this->model->getId() !== null) ? $this->model->getClassName() : null);
	}

	/**
	 * Can this particular user create an intent of this kind?
	 *
	 * @param Model_User $user
	 * @return bool
	 */
	public function canCreate(Model_User $user)
	{
		return true;
	}

	public function canDelete(Model_User $user)
	{
		return false;
	}

	/**
	 * Can this particular user read this intent?
	 *
	 * @param Model_User $user
	 * @return bool
	 */
	public function canRead(Model_User $user)
	{
		if ($this->canUpdate($user) === true)
		{
			return true;
		}
		return false;
	}

	/**
	 * Can this particular user update this intent?
	 *
	 * @param Model_User $user
	 * @return bool
	 */
	public function canUpdate(Model_User $user)
	{
		return false;
	}

	/**
	 * Run some pre-requisite stuff.
	 *
	 * @param array $data
	 * @param Model_User $actor
	 * @throws IntentException
	 */
	public function create(array $data, Model_User $actor)
	{
		if ($this->model->getId() !== null)
		{
			throw new IntentException("You can't create a new intent with an existing intent model.");
		}
	}

	/**
	 * @param string $handler
	 * @param array $data
	 * @throws HttpStatusException
	 * @return string
	 */
	public final function deduplicateClear($handler, array $data)
	{
		Model_Intent::deleteByHash($handler, md5(json_encode($data)));
	}

	/**
	 * @param array $data
	 * @throws HttpStatusException
	 * @return string
	 */
	public final function deduplicate(array $data)
	{
		$hash = md5(json_encode($data));
		$intent = Model_Intent::findByHash($this->model->getUser(), $this->model->getHandler(), $hash);
		if ($intent !== null)
		{
			throw new HttpStatusException(409, "An intent similar to this already exists with ID " . $intent);
		}
		$this->model->setHash($hash);
	}

	/**
	 * @param array $data
	 * @param Model_User $actor
	 * @return void
	 */
	public function delete(array $data, Model_User $actor)
	{
		Model_Intent::delete($this->model);
	}

	/**
	 * @return string
	 */
	protected final function getHandlerName()
	{
		return strtolower(str_replace("Intent_", "", get_called_class()));
	}

	/**
	 * @return string
	 */
	public final function getHash()
	{
		return $this->model->getHash();
	}

	/**
	 * @return int
	 */
	public final function getId()
	{
		return $this->model->getId();
	}

	/**
	 * @param array $data
	 * @throws InvalidArgumentException
	 * @return void
	 */
	protected final function mergeData(array $data)
	{
		if (!empty($data[0]))
		{
			throw new InvalidArgumentException("Only associative arrays can be passed to Intent::mergeData.");
		}

		foreach ($data as $key => $value)
		{
			if (is_array($value) || is_object($value))
			{
				throw new InvalidArgumentException("Only key->value pairs can be passed to Intent::mergeData.");
			}
			elseif ($value === null)
			{
				unset($this->data[$key]);
			}
			else
			{
				$this->data->$key = $value;
			}
		}
	}

	/**
	 * @param Request_Internal $request
	 * @param Response $response
	 * @param ACL $acl
	 * @param boolean $internal
	 * @throws HttpStatusException
	 * @return array
	 */
	public function render(Request_Internal $request, Response &$response, ACL $acl, $internal = false)
	{
		$user = $this->model->getUser();
		if (empty($user))
		{
			throw new HttpStatusException(500, "Failed to get user for this intent.");
		}

		return array(
			"id" => $this->model->getId(),
			"user" => $user->render($request, $response, $acl, true),
			"handler" => $this->getHandlerName(),
			"data" => $this->data->render(),
			"state" => $this->model->getCleanState()
		);
	}

	/**
	 * @return void
	 */
	public function save()
	{
		$this->model->save();
		$this->data->save(($this->model->getId() !== null) ? $this->model->getClassName() : null);
	}

	/**
	 * @param string|null $state
	 * @return null|string
	 */
	public final function state($state = null)
	{
		switch ($state)
		{
			case static::STATE_OPEN:
			case static::STATE_ACCEPTED:
			case static::STATE_REJECTED:
				$this->model->setState($state);
			return null;
			case null:
				return $this->model->getState();
			default:
				throw new InvalidArgumentException("This state should be a valid Intent STATE constant.");
		}
	}

	/**
	 * @param array $data
	 * @param Model_User $actor
	 * @throws IntentException
	 */
	public function update(array $data, Model_User $actor)
	{
		if ($this->model->getId() === null)
		{
			throw new IntentException("You can't update an intent without an existing intent model.");
		}
	}
}