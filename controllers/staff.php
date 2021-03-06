<?php
/**
 * @author: KentProjects <developer@kentprojects.com>
 * @license: Copyright KentProjects
 * @link: http://kentprojects.com
 */
final class Controller_Staff extends Controller
{
	/**
	 * /staff
	 * /staff/:id
	 *
	 * @throws HttpStatusException
	 * @return void
	 */
	public function action_index()
	{
		if ($this->request->param("id") !== null)
		{
			/**
			 * /staff/:id
			 */
			$this->validateMethods(Request::GET, Request::PUT, Request::DELETE);

			$user = Model_Staff::getById($this->request->param("id"));
			if (empty($user))
			{
				throw new HttpStatusException(404, "Staff member not found.");
			}

			$isSelf = ($this->auth->getUser() !== null) ? ($this->auth->getUser()->getId() == $user->getId()) : false;

			if ($this->request->getMethod() === Request::PUT)
			{
				/**
				 * PUT /staff/:id
				 * Used to update staff!
				 */

				/**
				 * Validate that the user can update this staff profile.
				 */
				if (!$isSelf)
				{
					$this->validateUser(array(
						"entity" => "user/" . $user->getId(),
						"action" => ACL::UPDATE,
						"message" => "You do not have permission to update this user profile."
					));
				}

				$user->update($this->request->getPostData());
				$user->save();
			}
			elseif ($this->request->getMethod() === Request::DELETE)
			{
				/**
				 * DELETE /staff/:id
				 * Used to delete staff!
				 */

				/**
				 * Validate that the user can delete this staff profile.
				 */
				if (!$isSelf)
				{
					$this->validateUser(array(
						"entity" => "user/" . $user->getId(),
						"action" => ACL::DELETE,
						"message" => "You do not have permission to delete this user profile."
					));
				}

				throw new HttpStatusException(501, "Deleting a staff member is coming soon.");
			}

			/**
			 * GET /staff/:id
			 */

			$this->response->status(200);
			$this->response->body($user);
			return;
		}

		/**
		 * /staff
		 */
		$this->validateMethods(Request::GET);

		/**
		 * GET /staff
		 */

		if ($this->request->query("fields") !== null)
		{
			Model_Project::returnFields(explode(",", $this->request->query("fields")));
		}

		/**
		 * SELECT `user_id` FROM `User`
		 * WHERE `role` = 'staff' AND `status` = 1
		 */
		$query = new Query("user_id", "User");
		$query->where(array("field" => "role", "value" => "staff"));
		$query->where(array("field" => "status", "value" => 1));

		if ($this->request->query("year") !== null)
		{
			/**
			 * JOIN `User_Year_Map` USING (`user_id`)
			 * WHERE `User_Year_Map`.`year` = ?
			 */
			$query->join(array(
				"table" => "User_Year_Map",
				"how" => Query::USING,
				"field" => "user_id"
			));
			$query->where(array(
				"table" => "User_Year_Map",
				"field" => "year",
				"type" => "i",
				"value" => $this->request->query("year")
			));
		}

		if ($this->request->query("supervisor") !== null)
		{
			/**
			 * JOIN `User_Year_Map` USING (`user_id`)
			 * WHERE `User_Year_Map`.`role_supervisor` = TRUE
			 */
			if ($this->request->query("year") === null)
			{
				$query->join(array(
					"table" => "User_Year_Map",
					"how" => Query::USING,
					"field" => "user_id"
				));
			}
			$query->where(array(
				"table" => "User_Year_Map",
				"field" => "role_supervisor",
				"value" => 1
			));
		}

		$users = $query->execute()->singlevals();
		foreach ($users as $k => $user_id)
		{
			$users[$k] = Model_User::getById($user_id);
		}

		$this->response->status(200);
		$this->response->body($users);
	}
}