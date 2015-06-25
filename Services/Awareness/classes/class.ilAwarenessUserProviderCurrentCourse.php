<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Awareness/classes/class.ilAwarenessUserProvider.php");

/**
 * All members of the same courses/groups as the user
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 * @ingroup ServicesAwareness
 */
class ilAwarenessUserProviderCurrentCourse extends ilAwarenessUserProvider
{
	/**
	 * Get provider id
	 *
	 * @return string provider id
	 */
	function getProviderId()
	{
		return "crs_current";
	}

	/**
	 * Provider title (used in awareness overlay and in administration settings)
	 *
	 * @return string provider title
	 */
	function getTitle()
	{
		$this->lng->loadLanguageModule("crs");
		return $this->lng->txt("crs_awrn_current_course");
	}

	/**
	 * Provider info (used in administration settings)
	 *
	 * @return string provider info text
	 */
	function getInfo()
	{
		$this->lng->loadLanguageModule("crs");
		return $this->lng->txt("crs_awrn_current_course_info");
	}

	/**
	 * Get initial set of users
	 *
	 * @return array array of user IDs
	 */
	function getInitialUserSet()
	{
		global $ilDB, $tree;

		$ub = array();

		if ($this->getRefId() > 0)
		{
			$path = $tree->getPathFull($this->getRefId());
			foreach ($path as $p)
			{
				if ($p["type"] == "crs")
				{
					$set = $ilDB->query("SELECT DISTINCT usr_id FROM obj_members ".
						" WHERE obj_id = ".$ilDB->quote($p["obj_id"], "integer"));
					$ub = array();
					while ($rec = $ilDB->fetchAssoc($set))
					{
						$ub[] = $rec["usr_id"];
					}

				}
			}
		}
		return $ub;
	}
}
?>