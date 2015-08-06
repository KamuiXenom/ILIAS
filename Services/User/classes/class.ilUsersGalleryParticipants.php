<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once 'Services/User/classes/class.ilAbstractGalleryUsers.php';

/**
 * Class ilUsersGalleryParticipants
 */
class ilUsersGalleryParticipants extends ilAbstractGalleryUsers
{
	/**
	 * @var ilParticipants
	 */
	protected $participants;

	/**
	 * @param ilParticipants $participants
	 */
	public function __construct(ilParticipants $participants)
	{
		$this->participants = $participants;
	}

	/**
	 * @param array $users
	 * @return array
	 */
	protected function getSortedUsers(array $users)
	{
		$participants_data = array();
		foreach($users as $users_id)
		{
			/**
			 * @var $user ilObjUser
			 */
			if(!($user = ilObjectFactory::getInstanceByObjId($users_id, false)))
			{
				continue;
			}

			if(!$user->getActive())
			{
				continue;
			}

			$participants_data[$user->getId()] = array(
				'id'   => $user->getId(),
				'user' => $user
			);
		}
		$participants_data = $this->collectUserDetails($participants_data);
		$participants_data = ilUtil::sortArray($participants_data, 'sort', 'asc');
		return $participants_data;
	}

	/**
	 * @return array
	 */
	public function getGalleryUsers()
	{
		$ordered_user = $this->getSortedUsers($this->participants->getAdmins());
		$ordered_user = $ordered_user + $this->getSortedUsers($this->participants->getTutors());
		$ordered_user = $ordered_user + $this->getSortedUsers($this->participants->getMembers());
		return $ordered_user;
	}
} 