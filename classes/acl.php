<?php
/**
 * @author: KentProjects <developer@kentprojects.com>
 * @license: Copyright KentProjects
 * @link: http://kentprojects.com
 */
final class ACL
{
	const CREATE = "acl:create";
	const READ = "acl:read";
	const UPDATE = "acl:update";
	const DELETE = "acl:delete";

	protected static $template = array("create" => 0, "read" => 0, "update" => 0, "delete" => 0);

	protected $acl;
	protected $user;

	public function __construct(Model_User $user = null)
	{
		if (empty($user))
		{
			return;
		}

		$this->user = $user;
		$this->fetch();
	}

	/**
	 * @param string $entity
	 * @return array
	 */
	public function checkMatch($entity)
	{
		if (empty($this->user))
		{
			return array();
		}

		$range = explode("/", $entity);
		$rangeString = "";
		$values = array();
		foreach ($range as $i => $piece)
		{
			$rangeString .= ($i == 0 ? "" : "/") . $piece;
			$values[] = $this->checkExactMatch($rangeString);
		}
		$values[] = static::$template;

		return call_user_func_array("array_merge", array_reverse($values));
	}

	/**
	 * @param string $entry
	 * @return array
	 */
	protected function checkExactMatch($entry)
	{
		foreach ($this->acl as $entity => $acl)
		{
			if ($entity === $entry)
			{
				return $acl;
			}
		}
		return array();
	}

	public function fetch()
	{
		if (empty($this->user))
		{
			return;
		}

		$this->acl = array();
		$control = Database::prepare(
			"SELECT `entity`, `create`, `read`, `update`, `delete` FROM `ACL` WHERE `user_id` = ?", "i"
		)->execute($this->user->getId())->as_assoc()->all();

		foreach ($control as $acl)
		{
			$entity = $acl["entity"];
			unset($acl["entity"]);
			$this->acl[$entity] = $acl;
		}

		ksort($this->acl);
	}

	/**
	 * Save the user's permissions.
	 * @return void
	 */
	public function save()
	{
		if (empty($this->user))
		{
			return;
		}
	}

	/**
	 * Update a user's permissions for something.
	 *
	 * @param string $string
	 * @param bool $create
	 * @param bool $read
	 * @param bool $update
	 * @param bool $delete
	 * @return void
	 */
	public function update($string, $create = false, $read = false, $update = false, $delete = false)
	{
		if (empty($this->user))
		{
			return;
		}

		$this->acl[$string] = array(
			"create" => $create ? 1 : 0,
			"read" => $read ? 1 : 0,
			"update" => $update ? 1 : 0,
			"delete" => $delete ? 1 : 0
		);

		ksort($this->acl);
	}

	/**
	 * @param string $entity
	 * @param string $action
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function validate($entity, $action)
	{
		if (strpos($action, "acl:") !== 0)
		{
			throw new InvalidArgumentException("Bad action ('{$action}') passed to ACL::validate.");
		}

		if (empty($this->user))
		{
			return false;
		}

		$action = str_replace("acl:", "", $action);
		$control = $this->checkMatch(strtolower($entity));
		Log::debug($entity, $action, $control);

		return array_key_exists($action, $control) && ($control[$action] == 1);
	}
}