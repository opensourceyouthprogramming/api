<?php
/**
 * @author: KentProjects <developer@kentprojects.com>
 * @license: Copyright KentProjects
 * @link: http://kentprojects.com
 */
final class Controller_Student extends Controller
{
	/**
	 * /student
	 * /student/:id
	 *
	 * @throws HttpStatusException
	 * @return void
	 */
	public function action_index()
	{
		$this->validateMethods(Request::GET, Request::PUT, Request::DELETE);

		if ($this->request->param("id") === null)
		{
			throw new HttpStatusException(400, "No student id provided.");
		}

		$user = Model_Student::getById($this->request->param("id"));
		if (empty($user))
		{
			throw new HttpStatusException(404, "Student not found.");
		}

		$isSelf = ($this->auth->getUser() !== null) ? ($this->auth->getUser()->getId() == $user->getId()) : false;

		if ($this->request->getMethod() === Request::PUT)
		{
			/**
			 * PUT /student/:id
			 * Used to update the student profile.
			 */

			/**
			 * Validate that the user can update this student profile.
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
			 * DELETE /student/:id
			 * Used to delete the student.
			 */

			/**
			 * Validate that the user can update this student profile.
			 */
			if (!$isSelf)
			{
				$this->validateUser(array(
					"entity" => "user/" . $user->getId(),
					"action" => ACL::DELETE,
					"message" => "You do not have permission to delete this user profile."
				));
			}

			throw new HttpStatusException(501, "Deleting student profiles is coming soon.");
		}
		else
		{
			$user->getGroup();
		}

		/**
		 * GET /student/:id
		 */

		$this->response->status(200);
		$this->response->body($user);
	}
}